from __future__ import annotations

import asyncio
import json
import logging
import os
import re
import signal
import threading
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from typing import Any

import aiohttp

from agent.fspbx_client import FspbxClient
from agent.settings import Settings

logger = logging.getLogger("fspbx-ai-receptionist")
logging.basicConfig(level=os.getenv("AI_RECEPTIONIST_LOG_LEVEL", "INFO"))

CALL_CHANGING_TOOLS = {
    "transfer_call",
    "warm_transfer_call",
    "complete_warm_transfer",
    "cancel_warm_transfer",
    "end_call",
}

def openai_realtime_headers() -> dict[str, str]:
    return {
        "Authorization": f"Bearer {os.environ['OPENAI_API_KEY']}",
    }


def sip_header_map(headers: list[dict[str, Any]]) -> dict[str, str]:
    mapped: dict[str, str] = {}
    for header in headers:
        name = str(header.get("name") or "").strip().lower()
        value = str(header.get("value") or "").strip()
        if name:
            mapped[name] = value
    return mapped


def metadata_from_headers(headers: dict[str, str]) -> dict[str, str | None]:
    return {
        "ai_receptionist_uuid": headers.get("x-fspbx-ai-receptionist-uuid"),
        "ai_receptionist_freeswitch_uuid": headers.get("x-fspbx-freeswitch-uuid"),
        "ai_receptionist_caller_id_name": headers.get("x-fspbx-caller-id-name"),
        "ai_receptionist_caller_id_number": headers.get("x-fspbx-caller-id-number"),
        "ai_receptionist_destination_number": headers.get("x-fspbx-destination-number"),
        "sip_call_id": headers.get("call-id"),
    }


def receptionist_instructions(config: dict[str, Any]) -> str:
    parts = [
        config.get("system_prompt"),
        config.get("routing_instructions"),
        "You may route calls only by using resolve_route, transfer_call, warm_transfer_call, complete_warm_transfer, cancel_warm_transfer, send_route_email, or end_call.",
        "When a caller confirms that an issue is resolved, ask if there is anything else you can help with before ending the call.",
        "Only call end_call after the caller clearly says they need nothing else, says goodbye, or otherwise indicates the conversation is finished.",
        "When the conversation is complete, you must call end_call after your final spoken message. Never call end_call as a silent response. Saying goodbye does not disconnect the phone call. Never leave a completed call connected.",
        "Before calling transfer_call or warm_transfer_call, tell the caller you are connecting them now and ask them to hold. Do not start a transfer silently.",
        "Use run_http_tool only for tools included in the current FS PBX configuration.",
    ]
    return "\n\n".join(str(part) for part in parts if part) or "You are a helpful phone receptionist."


def playback_delay_seconds(transcript: str, *, minimum: float = 1.0) -> float:
    words = re.findall(r"\b[\w']+\b", transcript)
    if not words:
        return minimum

    # Phone TTS usually lands around 130-170 words per minute. Add a little pad
    # so FreeSWITCH actions do not clip the tail of the generated audio.
    return min(max((len(words) / 2.4) + 0.75, minimum), 8.0)


def initial_response_delay_seconds() -> float:
    raw_delay = os.getenv("OPENAI_REALTIME_INITIAL_RESPONSE_DELAY_SECONDS", "0.8")
    try:
        delay = float(raw_delay)
    except ValueError:
        delay = 0.8

    return min(max(delay, 0.0), 3.0)


def quick_accept_payload() -> dict[str, Any]:
    return {
        "type": "realtime",
        "model": os.getenv("OPENAI_REALTIME_MODEL", "gpt-realtime-2"),
        "instructions": "You are a helpful phone receptionist.",
        "audio": {
            "input": {
                "transcription": {
                    "model": os.getenv("OPENAI_REALTIME_TRANSCRIPTION_MODEL", "gpt-4o-mini-transcribe"),
                },
            },
            "output": {
                "voice": os.getenv("OPENAI_REALTIME_VOICE", "marin"),
            },
        },
    }


def accept_payload(config: dict[str, Any]) -> dict[str, Any]:
    provider_config = (config.get("settings") or {}).get("provider_config") or {}
    payload: dict[str, Any] = {
        "type": "realtime",
        "model": provider_config.get("openai_realtime_model", "gpt-realtime-2"),
        "instructions": receptionist_instructions(config),
        "audio": {
            "input": {
                "transcription": {
                    "model": provider_config.get(
                        "openai_realtime_transcription_model",
                        os.getenv("OPENAI_REALTIME_TRANSCRIPTION_MODEL", "gpt-4o-mini-transcribe"),
                    ),
                },
            },
            "output": {
                "voice": config.get("openai_voice") or os.getenv("OPENAI_REALTIME_VOICE", "marin"),
            },
        },
        "tools": [
            {
                "type": "function",
                "name": "resolve_route",
                "description": "Find a configured AI Receptionist route by caller intent.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "intent": {"type": "string"},
                    },
                    "required": ["intent"],
                },
            },
            {
                "type": "function",
                "name": "resolve_destination",
                "description": "Find a PBX destination by caller intent, name, or extension when no configured route applies.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "intent": {"type": "string"},
                    },
                    "required": ["intent"],
                },
            },
            {
                "type": "function",
                "name": "transfer_call",
                "description": "Cold transfer the original FreeSWITCH caller A-leg to an approved PBX destination. Tell the caller you are connecting them before using this tool.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "destination_type": {"type": "string"},
                        "target": {"type": "string"},
                    },
                    "required": ["destination_type", "target"],
                },
            },
            {
                "type": "function",
                "name": "warm_transfer_call",
                "description": "Start a live warm transfer to a configured direct recipient. Tell the caller you are connecting them and ask them to hold before using this tool.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "route_uuid": {"type": "string"},
                        "handoff_summary": {"type": "string"},
                    },
                    "required": ["route_uuid", "handoff_summary"],
                },
            },
            {
                "type": "function",
                "name": "complete_warm_transfer",
                "description": "Complete an active warm transfer only after the recipient explicitly accepts. Include the recipient's exact spoken acceptance.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "recipient_response": {
                            "type": "string",
                            "description": "The exact words the recipient said to accept the call, such as 'Yes, I can take it.'",
                        },
                    },
                    "required": ["recipient_response"],
                },
            },
            {
                "type": "function",
                "name": "cancel_warm_transfer",
                "description": "Cancel an active warm transfer after the recipient declines or the AI should return to the caller.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "reason": {"type": "string"},
                    },
                },
            },
            {
                "type": "function",
                "name": "send_route_email",
                "description": "Send a message collected by the AI to a configured route email recipient.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "route_uuid": {"type": "string"},
                        "caller_name": {"type": "string"},
                        "caller_number": {"type": "string"},
                        "message": {"type": "string"},
                        "urgency": {"type": "string"},
                    },
                    "required": ["route_uuid", "message"],
                },
            },
            {
                "type": "function",
                "name": "end_call",
                "description": "Disconnect the active phone call. Use only after the caller indicates the conversation is finished and after your final spoken message.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "reason": {
                            "type": "string",
                            "description": "Short reason for ending the call, such as conversation_complete or caller_finished.",
                        },
                    },
                },
            },
            {
                "type": "function",
                "name": "run_http_tool",
                "description": "Run a domain-approved generic HTTP tool through FS PBX.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "tool_name": {"type": "string"},
                        "payload": {"type": "object"},
                    },
                    "required": ["tool_name"],
                },
            },
        ],
        "tool_choice": "auto",
    }

    if not config.get("tool_access_enabled", True):
        payload.pop("tools")
        payload["tool_choice"] = "none"

    return payload


class RealtimeCallController:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings

    async def accept_incoming_call(self, payload: dict[str, Any]) -> str:
        call_id = str(payload.get("call_id") or "")
        if not call_id:
            raise RuntimeError("OpenAI realtime call missing call_id.")

        headers = sip_header_map(payload.get("sip_headers") or [])
        receptionist_uuid = metadata_from_headers(headers).get("ai_receptionist_uuid")
        accept_config = quick_accept_payload()

        if receptionist_uuid:
            client = FspbxClient(self.settings.fspbx_base_url, self.settings.fspbx_agent_token)
            try:
                accept_config = accept_payload(await client.get_config(receptionist_uuid))
            finally:
                await client.close()

        async with aiohttp.ClientSession(headers=openai_realtime_headers()) as http:
            await self.accept_call(http, call_id, accept_config)

        return call_id

    async def handle_accepted_call(self, payload: dict[str, Any]) -> None:
        call_id = str(payload.get("call_id") or "")
        headers = sip_header_map(payload.get("sip_headers") or [])
        metadata = metadata_from_headers(headers)
        receptionist_uuid = metadata.get("ai_receptionist_uuid")

        if not call_id or not receptionist_uuid:
            logger.warning("OpenAI realtime call missing call_id or AI receptionist UUID.")
            return

        client = FspbxClient(self.settings.fspbx_base_url, self.settings.fspbx_agent_token)
        session_uuid: str | None = None
        transcript: list[str] = []

        try:
            async with aiohttp.ClientSession(headers=openai_realtime_headers()) as http:
                config = await client.get_config(receptionist_uuid)
                session_response = await client.start_session(receptionist_uuid, {
                    "openai_call_id": call_id,
                    "sip_call_id": metadata.get("sip_call_id"),
                    "freeswitch_uuid": metadata.get("ai_receptionist_freeswitch_uuid"),
                    "caller_id_name": metadata.get("ai_receptionist_caller_id_name"),
                    "caller_id_number": metadata.get("ai_receptionist_caller_id_number"),
                    "destination_number": metadata.get("ai_receptionist_destination_number"),
                    "metadata": {
                        **payload,
                        "sip_header_map": headers,
                    },
                })
                session_uuid = session_response["session_uuid"]
                await self.monitor_call(http, call_id, client, session_uuid, config, transcript)

            await client.end_session(session_uuid, {
                "status": "completed",
                "transcript": "\n".join(transcript),
            })
        except Exception as exc:
            logger.exception("OpenAI realtime call %s failed", call_id)
            if session_uuid:
                await client.end_session(session_uuid, {
                    "status": "failed",
                    "transcript": "\n".join(transcript),
                    "error_message": str(exc),
                })
        finally:
            await client.close()

    async def accept_call(self, http: aiohttp.ClientSession, call_id: str, payload: dict[str, Any]) -> None:
        async with http.post(
            f"https://api.openai.com/v1/realtime/calls/{call_id}/accept",
            json=payload,
            headers={"Content-Type": "application/json"},
        ) as response:
            body = await response.text()
            logger.info(
                "OpenAI accept call %s returned %s location=%s body=%s",
                call_id,
                response.status,
                response.headers.get("Location"),
                body[:500],
            )
            if response.status >= 400:
                raise RuntimeError(f"OpenAI accept call failed with {response.status}: {body}")

    async def monitor_call(
        self,
        http: aiohttp.ClientSession,
        call_id: str,
        client: FspbxClient,
        session_uuid: str,
        config: dict[str, Any],
        transcript: list[str],
    ) -> None:
        url = f"wss://api.openai.com/v1/realtime?call_id={call_id}"
        pending_call_changing_tools: list[dict[str, Any]] = []
        post_response_call_changing_tools: list[dict[str, Any]] = []
        latest_assistant_transcript = [""]
        assistant_transcript_version = [0]
        handled_function_calls: set[str] = set()

        for attempt in range(1, 6):
            try:
                async with http.ws_connect(url, headers=openai_realtime_headers()) as ws:
                    initial_message = config.get("initial_message")
                    if initial_message:
                        await asyncio.sleep(initial_response_delay_seconds())
                        await ws.send_json({
                            "type": "response.create",
                            "response": {"instructions": initial_message},
                        })

                    async for message in ws:
                        if message.type == aiohttp.WSMsgType.TEXT:
                            event = json.loads(message.data)
                            await self.handle_event(
                                ws,
                                event,
                                client,
                                session_uuid,
                                transcript,
                                pending_call_changing_tools,
                                post_response_call_changing_tools,
                                latest_assistant_transcript,
                                assistant_transcript_version,
                                handled_function_calls,
                            )
                        elif message.type in (aiohttp.WSMsgType.CLOSED, aiohttp.WSMsgType.ERROR):
                            break

                    return
            except aiohttp.WSServerHandshakeError as exc:
                logger.warning(
                    "OpenAI monitor websocket for %s failed on attempt %s with status %s headers=%s",
                    call_id,
                    attempt,
                    exc.status,
                    dict(exc.headers or {}),
                )
                if exc.status != 404 or attempt == 5:
                    raise

                await asyncio.sleep(min(attempt * 0.5, 2))

    async def handle_event(
        self,
        ws: aiohttp.ClientWebSocketResponse,
        event: dict[str, Any],
        client: FspbxClient,
        session_uuid: str,
        transcript: list[str],
        pending_call_changing_tools: list[dict[str, Any]],
        post_response_call_changing_tools: list[dict[str, Any]],
        latest_assistant_transcript: list[str],
        assistant_transcript_version: list[int],
        handled_function_calls: set[str],
    ) -> None:
        event_type = event.get("type")

        if event_type == "conversation.item.input_audio_transcription.completed":
            transcript.append("Caller: " + str(event.get("transcript") or ""))
            return

        if event_type in {"response.output_audio_transcript.done", "response.audio_transcript.done"}:
            assistant_transcript = str(event.get("transcript") or "")
            latest_assistant_transcript[0] = assistant_transcript
            assistant_transcript_version[0] += 1
            transcript.append("Assistant: " + assistant_transcript)

            if post_response_call_changing_tools:
                await asyncio.sleep(playback_delay_seconds(assistant_transcript, minimum=0.5))
                await self.run_scheduled_function_calls(client, session_uuid, post_response_call_changing_tools)
                return

            if pending_call_changing_tools:
                await asyncio.sleep(playback_delay_seconds(assistant_transcript, minimum=0.5))
                await self.run_pending_function_calls(ws, client, session_uuid, transcript, pending_call_changing_tools)
                return

            return

        if event_type in {"response.done", "response.completed"} and post_response_call_changing_tools:
            await asyncio.sleep(0.35)
            await self.run_scheduled_function_calls(client, session_uuid, post_response_call_changing_tools)
            return

        if event_type in {"response.done", "response.completed"} and pending_call_changing_tools:
            tool_only_end_calls = [
                call for call in pending_call_changing_tools
                if call["name"] == "end_call"
                and call.get("_queued_after_transcript_version", 0) >= assistant_transcript_version[0]
            ]
            if tool_only_end_calls:
                remaining_calls = [
                    call for call in pending_call_changing_tools
                    if call not in tool_only_end_calls
                ]
                pending_call_changing_tools.clear()
                pending_call_changing_tools.extend(remaining_calls)
                post_response_call_changing_tools.extend(tool_only_end_calls)

                for call in tool_only_end_calls:
                    await self.acknowledge_deferred_end_call(ws, call)

                await ws.send_json({
                    "type": "response.create",
                    "response": {
                        "instructions": (
                            "Say one brief, natural closing sentence to the caller, then stop. "
                            "Do not call any tools in this response."
                        ),
                    },
                })
                return

            queued_before_latest_transcript = any(
                call.get("_queued_after_transcript_version", 0) < assistant_transcript_version[0]
                for call in pending_call_changing_tools
            )
            delay = playback_delay_seconds(latest_assistant_transcript[0], minimum=0.5) if queued_before_latest_transcript else 0.35
            await asyncio.sleep(delay)
            await self.run_pending_function_calls(ws, client, session_uuid, transcript, pending_call_changing_tools)
            return

        call = self.function_call_from_event(event)
        if not call:
            return

        call_key = self.function_call_key(call)
        if call_key in handled_function_calls:
            logger.info(
                "Ignoring duplicate AI Receptionist function call %s for session %s.",
                call["name"],
                session_uuid,
            )
            return

        handled_function_calls.add(call_key)

        if call["name"] in CALL_CHANGING_TOOLS:
            logger.info("Deferring AI Receptionist call-changing tool %s for session %s.", call["name"], session_uuid)
            call["_queued_after_transcript_version"] = assistant_transcript_version[0]
            pending_call_changing_tools.append(call)
            return

        await self.run_function_call_and_respond(ws, client, session_uuid, transcript, call)

    async def run_pending_function_calls(
        self,
        ws: aiohttp.ClientWebSocketResponse,
        client: FspbxClient,
        session_uuid: str,
        transcript: list[str],
        pending_calls: list[dict[str, Any]],
    ) -> None:
        calls = pending_calls[:]
        pending_calls.clear()

        for call in calls:
            await self.run_function_call_and_respond(ws, client, session_uuid, transcript, call)

    async def acknowledge_deferred_end_call(
        self,
        ws: aiohttp.ClientWebSocketResponse,
        call: dict[str, Any],
    ) -> None:
        await ws.send_json({
            "type": "conversation.item.create",
            "item": {
                "type": "function_call_output",
                "call_id": call["call_id"],
                "output": json.dumps({
                    "success": True,
                    "status": "waiting_for_final_message",
                    "message": "Say a brief final message before the call is disconnected.",
                }),
            },
        })

    async def run_scheduled_function_calls(
        self,
        client: FspbxClient,
        session_uuid: str,
        scheduled_calls: list[dict[str, Any]],
    ) -> None:
        calls = scheduled_calls[:]
        scheduled_calls.clear()

        for call in calls:
            await self.run_function_call(client, session_uuid, call["name"], call["arguments"])

    async def run_function_call_and_respond(
        self,
        ws: aiohttp.ClientWebSocketResponse,
        client: FspbxClient,
        session_uuid: str,
        transcript: list[str],
        call: dict[str, Any],
    ) -> None:
        result = await self.run_function_call(client, session_uuid, call["name"], call["arguments"], transcript)
        await ws.send_json({
            "type": "conversation.item.create",
            "item": {
                "type": "function_call_output",
                "call_id": call["call_id"],
                "output": json.dumps(result),
            },
        })

        response: dict[str, Any] = {}
        if result.get("response_instructions"):
            response["instructions"] = str(result["response_instructions"])

        await ws.send_json({
            "type": "response.create",
            **({"response": response} if response else {}),
        })

    def function_call_from_event(self, event: dict[str, Any]) -> dict[str, Any] | None:
        if event.get("type") == "response.function_call_arguments.done":
            return {
                "name": event.get("name"),
                "call_id": event.get("call_id"),
                "arguments": self.decode_arguments(event.get("arguments")),
            }

        item = event.get("item") or {}
        if event.get("type") == "response.output_item.done" and item.get("type") == "function_call":
            return {
                "name": item.get("name"),
                "call_id": item.get("call_id"),
                "arguments": self.decode_arguments(item.get("arguments")),
            }

        return None

    def function_call_key(self, call: dict[str, Any]) -> str:
        call_id = str(call.get("call_id") or "").strip()
        if call_id:
            return call_id

        return json.dumps({
            "name": call.get("name"),
            "arguments": call.get("arguments") or {},
        }, sort_keys=True)

    def decode_arguments(self, raw: Any) -> dict[str, Any]:
        if isinstance(raw, dict):
            return raw
        if not raw:
            return {}
        return json.loads(str(raw))

    async def run_function_call(
        self,
        client: FspbxClient,
        session_uuid: str,
        name: str,
        arguments: dict[str, Any],
        transcript: list[str] | None = None,
    ) -> dict[str, Any]:
        if name == "resolve_route":
            return await client.resolve_route(session_uuid, {"intent": arguments.get("intent", "")})

        if name == "resolve_destination":
            return await client.resolve_destination(session_uuid, {"intent": arguments.get("intent", "")})

        if name == "transfer_call":
            destination = await client.resolve_destination(session_uuid, {
                "type": arguments.get("destination_type"),
                "target": arguments.get("target"),
            })
            return await client.transfer(session_uuid, destination.get("destination") or destination)

        if name == "warm_transfer_call":
            return await client.warm_transfer(
                session_uuid,
                str(arguments.get("route_uuid") or ""),
                str(arguments.get("handoff_summary") or ""),
            )

        if name == "complete_warm_transfer":
            return await client.complete_warm_transfer(
                session_uuid,
                str(arguments.get("recipient_response") or ""),
            )

        if name == "cancel_warm_transfer":
            return await client.cancel_warm_transfer(
                session_uuid,
                str(arguments.get("reason") or "declined"),
            )

        if name == "send_route_email":
            return await client.send_route_email(session_uuid, {
                "route_uuid": arguments.get("route_uuid"),
                "caller_name": arguments.get("caller_name"),
                "caller_number": arguments.get("caller_number"),
                "message": arguments.get("message"),
                "urgency": arguments.get("urgency"),
                "transcript": "\n".join(transcript or []),
            })

        if name == "end_call":
            return await client.end_call(
                session_uuid,
                str(arguments.get("reason") or "conversation_complete"),
            )

        if name == "run_http_tool":
            return await client.run_tool(
                session_uuid,
                str(arguments.get("tool_name") or ""),
                arguments.get("payload") or {},
            )

        return {"success": False, "error": f"Unknown tool {name}"}


def run_async_call(controller: RealtimeCallController, payload: dict[str, Any]) -> None:
    asyncio.run(controller.handle_accepted_call(payload))


class RequestHandler(BaseHTTPRequestHandler):
    controller: RealtimeCallController
    token: str

    def do_GET(self) -> None:
        if self.path != "/health":
            self.send_json({"message": "Not found."}, HTTPStatus.NOT_FOUND)
            return

        self.send_json({"status": "ok", "service": "ai-receptionist-agent"})

    def do_POST(self) -> None:
        if self.path != "/calls":
            self.send_json({"message": "Not found."}, HTTPStatus.NOT_FOUND)
            return

        if self.headers.get("Authorization") != f"Bearer {self.token}":
            self.send_json({"message": "Unauthorized."}, HTTPStatus.UNAUTHORIZED)
            return

        length = int(self.headers.get("Content-Length") or "0")
        payload = json.loads(self.rfile.read(length) or b"{}")

        try:
            asyncio.run(self.controller.accept_incoming_call(payload))
        except Exception as exc:
            logger.exception("OpenAI realtime call accept failed")
            self.send_json({"message": "OpenAI call accept failed.", "error": str(exc)}, HTTPStatus.BAD_GATEWAY)
            return

        thread = threading.Thread(target=run_async_call, args=(self.controller, payload), daemon=True)
        thread.start()

        self.send_json({"message": "Call accepted for OpenAI Realtime processing."}, HTTPStatus.ACCEPTED)

    def send_json(self, payload: dict[str, Any], status: HTTPStatus = HTTPStatus.OK) -> None:
        body = json.dumps(payload).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, format: str, *args: object) -> None:
        return


def main() -> None:
    settings = Settings.from_env()
    if not os.getenv("OPENAI_API_KEY"):
        raise RuntimeError("OPENAI_API_KEY must be set for the AI Receptionist controller.")

    RequestHandler.controller = RealtimeCallController(settings)
    RequestHandler.token = settings.fspbx_agent_token
    server = ThreadingHTTPServer((settings.health_host, settings.health_port), RequestHandler)

    def shutdown(*_: object) -> None:
        server.shutdown()

    signal.signal(signal.SIGTERM, shutdown)
    signal.signal(signal.SIGINT, shutdown)
    logger.info("AI Receptionist OpenAI Realtime controller listening on %s:%s", settings.health_host, settings.health_port)
    server.serve_forever()


if __name__ == "__main__":
    main()
