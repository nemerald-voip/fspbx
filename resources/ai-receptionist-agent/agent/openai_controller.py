from __future__ import annotations

import asyncio
import json
import logging
import os
import re
import signal
import threading
from dataclasses import dataclass, field
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

CONSULT_TOOL_NAMES = {"complete_warm_transfer", "cancel_warm_transfer"}


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
        "CRITICAL ROUTING RULES:",
        "- Routing is ONLY allowed via configured routes (the list above). Pick the matching route_uuid and pass it to transfer_call (cold), warm_transfer_call (warm), or send_route_email (email).",
        "- If the caller's intent does not clearly match any configured route, do NOT guess and do NOT invent a destination. Ask one clarifying question to find a fit, or take a message via send_route_email on the most appropriate route.",
        "- resolve_destination + transfer_call (without route_uuid) are ONLY for the case where the caller has EXPLICITLY named a specific extension number, queue, or person they want to reach (e.g. \"transfer me to extension 201\", \"I'd like to speak to Dexter\"). NEVER use them as a fallback when resolve_route returned no match.",
        "CRITICAL TOOL RULES:",
        "- Every transfer_call or warm_transfer_call invocation MUST be preceded by spoken audio in the SAME response. Tell the caller you are connecting them now and ask them to hold, then call the tool. Calling a transfer tool without speaking first is forbidden — the system will reject it and ask you to announce first.",
        "- Example pattern: First say a one-sentence announcement to the caller, such as \"Connecting you to support now, please hold.\" THEN immediately call warm_transfer_call with the route_uuid.",
        "- Every end_call invocation MUST be preceded by a brief spoken closing in the SAME response. Calling end_call silently is forbidden. Saying goodbye in conversation does not disconnect the call on its own — you must call end_call after your final spoken sentence.",
        "- You may route calls only by using resolve_route, resolve_destination, transfer_call, warm_transfer_call, complete_warm_transfer, cancel_warm_transfer, send_route_email, or end_call.",
        "- Use run_http_tool only for tools included in the current FS PBX configuration.",
        "CONVERSATION RULES:",
        "- When a caller confirms that an issue is resolved, ask if there is anything else you can help with before ending the call.",
        "- Only call end_call after the caller clearly says they need nothing else, says goodbye, or otherwise indicates the conversation is finished.",
        "- Never leave a completed call connected.",
    ]
    return "\n\n".join(str(part) for part in parts if part) or "You are a helpful phone receptionist."


def consult_persona_instructions(team: str, handoff_summary: str) -> str:
    summary = handoff_summary if handoff_summary else "The caller asked to be connected."
    team_label = team or "the requested team"
    return "\n".join([
        f"You are consulting with the transfer recipient for {team_label}.",
        "The original caller is parked on hold and cannot hear you. Only the recipient hears you.",
        f"Handoff context: {summary}",
        "Speak in the language you were using with the original caller, unless the recipient clearly prefers another.",
        "Only two outcomes are allowed:",
        "- If the recipient accepts (in any language), call complete_warm_transfer with the recipient's exact spoken response in recipient_response.",
        "- If the recipient declines, is unavailable, or asks to call back later, call cancel_warm_transfer with reason \"declined\".",
        "Do not call any other tool while consulting.",
        "Do not narrate your own state. Do not say you are waiting. Do not say you are still trying to reach anyone. Do not describe what you just did.",
        "Do not speak to the recipient as if they were the original caller.",
    ])


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


def recipient_consult_response_delay_seconds() -> float:
    # Default raised to 3.0: the recipient leg's bridge to the OpenAI leg and the
    # consult-mode session.update both need a moment to fully take effect on the
    # OpenAI server before the briefing response.create is requested. Shorter
    # delays produced cases where the briefing response generated no audible
    # output on the recipient's leg.
    raw_delay = os.getenv("OPENAI_REALTIME_RECIPIENT_CONSULT_DELAY_SECONDS", "3.0")
    try:
        delay = float(raw_delay)
    except ValueError:
        delay = 3.0

    return min(max(delay, 0.0), 5.0)


def bridge_audio_drain_seconds() -> float:
    raw_delay = os.getenv("OPENAI_REALTIME_BRIDGE_DRAIN_SECONDS", "0.3")
    try:
        delay = float(raw_delay)
    except ValueError:
        delay = 0.3

    return min(max(delay, 0.0), 2.0)


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
                "description": "Look up a configured AI Receptionist route by caller intent. Returns the route, including route_uuid. Every configured route is also listed (with its route_uuid) in your system instructions, so you may pick a route_uuid directly from there instead of calling this tool.",
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
                "description": "Look up a PBX destination by extension or name. ONLY use when the caller has EXPLICITLY named a specific extension number, queue, or person they want to reach (e.g. \"transfer me to extension 201\", \"I'd like to speak to Dexter\"). NEVER use this as a fallback when resolve_route returns no match — for an unmatched intent, ask the caller for clarification or take a message via send_route_email instead.",
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
                "description": "Cold transfer the caller. Provide EITHER route_uuid (preferred — for a configured route) OR destination_type + target (only when the caller explicitly named an extension/queue/person and you looked it up via resolve_destination). NEVER guess destination_type/target values, and never use destination_type/target as a fallback when no route matches. PRECONDITION (REQUIRED): in the SAME response, first speak a brief announcement to the caller telling them you are connecting them, then invoke this tool. Calling this tool without preceding spoken audio is forbidden and the system will reject it.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "route_uuid": {
                            "type": "string",
                            "description": "The route_uuid of a configured cold-transfer route, taken from your system instructions or from resolve_route. Use this whenever the caller's intent matches a configured route.",
                        },
                        "destination_type": {
                            "type": "string",
                            "description": "Only when the caller explicitly named an extension or queue. Pair with target. Must come from resolve_destination output.",
                        },
                        "target": {
                            "type": "string",
                            "description": "Only when the caller explicitly named an extension or queue. Pair with destination_type. Must come from resolve_destination output.",
                        },
                    },
                },
            },
            {
                "type": "function",
                "name": "warm_transfer_call",
                "description": "Start a live warm transfer to a configured direct recipient. PRECONDITION (REQUIRED): in the SAME response, first speak a brief one-sentence announcement to the caller (for example, \"Connecting you to support now, please hold\") and then invoke this tool. Calling this tool without preceding spoken audio is forbidden and the system will reject it.",
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


@dataclass
class CallContext:
    config: dict[str, Any]
    session_uuid: str
    transcript: list[str]
    ws_lock: asyncio.Lock
    base_instructions: str
    base_tools: list[dict[str, Any]] | None
    base_tool_choice: str
    base_audio: dict[str, Any] = field(default_factory=dict)
    pending_call_changing_tools: list[dict[str, Any]] = field(default_factory=list)
    post_response_call_changing_tools: list[dict[str, Any]] = field(default_factory=list)
    latest_assistant_transcript: str = ""
    assistant_transcript_version: int = 0
    response_completed: bool = True
    response_started_transcript_version: int = 0
    last_completed_response_transcript: str = ""
    handled_function_calls: set[str] = field(default_factory=set)
    in_consult: bool = False
    # True once the briefing response.done has fired after entering consult mode.
    # Speech_stopped only auto-triggers a response after this — otherwise the
    # recipient's "hello" before the briefing arrives makes us steal the turn
    # and collide with the briefing.
    consult_briefing_done: bool = False
    # output_audio_buffer.{started,stopped} let us know when the SIP playback buffer
    # has actually drained, which is the only reliable signal for "the caller heard
    # everything we just generated". Word-count heuristics on transcript.done get
    # the audio length wrong and cause the bridge swap to clip the tail.
    audio_output_active: bool = False
    response_had_audio_transcript: bool = False
    audio_buffer_observed: bool = False


class RealtimeCallController:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self._warm_transfer_watchers: set[str] = set()

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

    async def ws_send(self, ws: aiohttp.ClientWebSocketResponse, context: CallContext, payload: dict[str, Any]) -> None:
        async with context.ws_lock:
            if ws.closed:
                return
            try:
                await ws.send_json(payload)
            except Exception as exc:
                logger.debug("WS send failed for session %s: %s", context.session_uuid, exc)

    async def cancel_in_flight_response(self, ws: aiohttp.ClientWebSocketResponse, context: CallContext) -> None:
        """Cancel any in-flight assistant response and give RTP a moment to drain.

        Without this, audio still being generated by OpenAI keeps streaming over the
        SIP leg, and a subsequent bridge swap pipes the tail of that audio to whichever
        party is now connected — causing recipient-bound phrases to be heard by the
        caller (and vice versa).
        """
        if context.response_completed:
            return

        async with context.ws_lock:
            if not ws.closed:
                try:
                    await ws.send_json({"type": "response.cancel"})
                except Exception as exc:
                    logger.debug("response.cancel send failed for session %s: %s", context.session_uuid, exc)

        await asyncio.sleep(bridge_audio_drain_seconds())

    async def enter_consult_mode(
        self,
        ws: aiohttp.ClientWebSocketResponse,
        context: CallContext,
        route: dict[str, Any],
        handoff_summary: str,
    ) -> None:
        """Switch the session to consult-with-recipient persona + restricted tools."""
        if context.base_tools is None:
            # Tool access is disabled — warm transfer can't have started; nothing to do.
            return

        team = str(route.get("destination_label") or route.get("name") or "")
        instructions = consult_persona_instructions(team, handoff_summary)
        consult_tools = [t for t in context.base_tools if t.get("name") in CONSULT_TOOL_NAMES]

        session_update: dict[str, Any] = {
            # OpenAI Realtime requires session.type on every session.update; the
            # server rejects the whole event with "Missing required parameter:
            # 'session.type'." otherwise.
            "type": "realtime",
            "instructions": instructions,
            "tools": consult_tools,
            "tool_choice": "auto",
        }
        # When the SIP bridge swaps OpenAI's input from the caller to the recipient,
        # server VAD fires on the recipient's first audio (often silence or "hello")
        # and auto-creates a response that races against — and rejects — our briefing
        # response.create. Disable auto-response creation while we own the turn; we
        # manually trigger response.create on input_audio_buffer.speech_stopped events
        # for subsequent recipient turns, and restore_caller_session re-enables
        # create_response when we leave consult mode.
        #
        # In the gpt-realtime API turn_detection lives under audio.input, not at the
        # session root. Setting it at the root returns "Unknown parameter:
        # 'session.turn_detection'.".
        audio_config: dict[str, Any] = (
            {k: (dict(v) if isinstance(v, dict) else v) for k, v in (context.base_audio or {}).items()}
        )
        audio_input = dict(audio_config.get("input") or {})
        audio_input["turn_detection"] = {
            "type": "server_vad",
            "create_response": False,
            "interrupt_response": False,
        }
        audio_config["input"] = audio_input
        session_update["audio"] = audio_config

        await self.ws_send(ws, context, {
            "type": "session.update",
            "session": session_update,
        })
        context.in_consult = True
        context.consult_briefing_done = False
        logger.info(
            "AI Receptionist entered consult mode for session %s: team=%s, tools=%s.",
            context.session_uuid,
            team or "the requested team",
            [t.get("name") for t in consult_tools],
        )

    async def restore_caller_session(self, ws: aiohttp.ClientWebSocketResponse, context: CallContext) -> None:
        """Restore the original caller-receptionist persona + full tool list."""
        if not context.in_consult:
            return

        session_update: dict[str, Any] = {
            # session.type is required by the OpenAI Realtime server; omitting it
            # makes the session.update event get rejected with a missing-parameter
            # error.
            "type": "realtime",
            "instructions": context.base_instructions,
        }
        if context.base_tools is not None:
            session_update["tools"] = context.base_tools
            session_update["tool_choice"] = context.base_tool_choice or "auto"
        else:
            session_update["tool_choice"] = "none"
        # Re-enable VAD auto-response creation (we turned it off when entering
        # consult mode). The caller side of the conversation expects the AI to
        # auto-reply to caller turns. In the gpt-realtime API turn_detection lives
        # under audio.input, not at the session root.
        audio_config: dict[str, Any] = (
            {k: (dict(v) if isinstance(v, dict) else v) for k, v in (context.base_audio or {}).items()}
        )
        audio_input = dict(audio_config.get("input") or {})
        audio_input["turn_detection"] = {
            "type": "server_vad",
            "create_response": True,
            "interrupt_response": True,
        }
        audio_config["input"] = audio_input
        session_update["audio"] = audio_config

        await self.ws_send(ws, context, {
            "type": "session.update",
            "session": session_update,
        })
        context.in_consult = False
        context.consult_briefing_done = False

    async def monitor_call(
        self,
        http: aiohttp.ClientSession,
        call_id: str,
        client: FspbxClient,
        session_uuid: str,
        config: dict[str, Any],
        transcript: list[str],
    ) -> None:
        accept = accept_payload(config)
        context = CallContext(
            config=config,
            session_uuid=session_uuid,
            transcript=transcript,
            ws_lock=asyncio.Lock(),
            base_instructions=str(accept.get("instructions") or ""),
            base_tools=accept.get("tools"),
            base_tool_choice=str(accept.get("tool_choice") or "auto"),
            base_audio=accept.get("audio") or {},
        )

        url = f"wss://api.openai.com/v1/realtime?call_id={call_id}"

        for attempt in range(1, 6):
            try:
                async with http.ws_connect(url, headers=openai_realtime_headers()) as ws:
                    initial_message = config.get("initial_message")
                    if initial_message:
                        await asyncio.sleep(initial_response_delay_seconds())
                        await self.ws_send(ws, context, {
                            "type": "response.create",
                            "response": {"instructions": initial_message},
                        })

                    async for message in ws:
                        if message.type == aiohttp.WSMsgType.TEXT:
                            event = json.loads(message.data)
                            await self.handle_event(ws, event, client, context)
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
        context: CallContext,
    ) -> None:
        event_type = event.get("type")

        if event_type == "error":
            # OpenAI Realtime reports protocol errors as a top-level event rather
            # than failing the WebSocket; surface them so failures (e.g. a
            # response.create rejected because a previous response is still in
            # flight, or a malformed session.update) don't disappear into the void.
            logger.warning(
                "OpenAI Realtime error for session %s: %s",
                context.session_uuid,
                event.get("error") or event,
            )
            return

        if event_type == "session.updated":
            # Confirms a session.update we sent was accepted by the server.
            logger.info(
                "AI Receptionist session.updated acknowledged for session %s.",
                context.session_uuid,
            )
            return

        if event_type == "input_audio_buffer.speech_stopped":
            # While in consult mode we run with turn_detection.create_response=false
            # so server VAD does not auto-create responses (that race blocks our
            # briefing). VAD still emits speech_started/speech_stopped though, so we
            # use speech_stopped as the signal that the recipient's turn ended and
            # manually trigger a response so the AI can reply (typically calling
            # complete_warm_transfer or cancel_warm_transfer based on the answer).
            #
            # CRITICAL: we must wait until the briefing response has actually been
            # delivered (consult_briefing_done) before honouring speech_stopped.
            # Otherwise the recipient's pre-briefing "hello" / mic noise fires
            # speech_stopped while response_completed is still True (the announcement
            # is done, the briefing has not been sent yet), and our handler steals
            # the turn — making our briefing response.create get rejected with
            # conversation_already_has_active_response.
            if (
                context.in_consult
                and context.consult_briefing_done
                and context.response_completed
                and not ws.closed
            ):
                logger.info(
                    "AI Receptionist consult: recipient finished a turn for session %s; "
                    "triggering response.create.",
                    context.session_uuid,
                )
                await self.ws_send(ws, context, {"type": "response.create"})
            return

        if event_type == "response.created":
            context.response_completed = False
            context.response_started_transcript_version = context.assistant_transcript_version
            context.last_completed_response_transcript = ""
            context.response_had_audio_transcript = False
            context.audio_buffer_observed = False
            return

        if event_type == "conversation.item.input_audio_transcription.completed":
            context.transcript.append("Caller: " + str(event.get("transcript") or ""))
            return

        if event_type in {"response.output_audio_transcript.done", "response.audio_transcript.done"}:
            assistant_transcript = str(event.get("transcript") or "")
            context.latest_assistant_transcript = assistant_transcript
            context.assistant_transcript_version += 1
            context.transcript.append("Assistant: " + assistant_transcript)
            context.response_had_audio_transcript = True
            # We no longer trigger deferred tools here. transcript.done fires when the
            # text is generated, which is earlier than when SIP audio finishes playing.
            # Wait for response.done + output_audio_buffer.stopped instead.
            return

        if event_type == "output_audio_buffer.started":
            context.audio_output_active = True
            context.audio_buffer_observed = True
            return

        if event_type == "output_audio_buffer.stopped":
            context.audio_output_active = False
            context.audio_buffer_observed = True
            if context.response_completed:
                await self.maybe_run_pending_tools(ws, client, context)
            return

        if event_type == "output_audio_buffer.cleared":
            context.audio_output_active = False
            context.audio_buffer_observed = True
            return

        if event_type in {"response.done", "response.completed"}:
            context.response_completed = True
            context.last_completed_response_transcript = (
                context.latest_assistant_transcript
                if context.assistant_transcript_version > context.response_started_transcript_version
                else ""
            )
            # The first response.done after entering consult mode is the briefing.
            # Until this fires, the speech_stopped handler must not steal the turn
            # by auto-creating a response — that would race the briefing and OpenAI
            # would reject one of them with conversation_already_has_active_response.
            if context.in_consult and not context.consult_briefing_done:
                context.consult_briefing_done = True
                logger.info(
                    "AI Receptionist consult: briefing response.done received for session %s.",
                    context.session_uuid,
                )

            if context.audio_output_active:
                # Audio is still being played out to the SIP peer. The deferred-tool
                # runner will be invoked by the output_audio_buffer.stopped handler.
                # Arm a fallback in case the stopped event never arrives.
                asyncio.create_task(self.audio_drain_fallback(ws, client, context))
                return

            await self.maybe_run_pending_tools(ws, client, context)
            return

        call = self.function_call_from_event(event)
        if not call:
            return

        call_key = self.function_call_key(call)
        if call_key in context.handled_function_calls:
            logger.info(
                "Ignoring duplicate AI Receptionist function call %s for session %s.",
                call["name"],
                context.session_uuid,
            )
            return

        context.handled_function_calls.add(call_key)

        if call["name"] in CALL_CHANGING_TOOLS:
            logger.info(
                "Deferring AI Receptionist call-changing tool %s for session %s.",
                call["name"],
                context.session_uuid,
            )
            call["_queued_after_transcript_version"] = context.assistant_transcript_version
            context.pending_call_changing_tools.append(call)

            if context.response_completed and not context.audio_output_active:
                await self.maybe_run_pending_tools(ws, client, context)

            return

        await self.run_function_call_and_respond(ws, client, context, call)

    async def maybe_run_pending_tools(
        self,
        ws: aiohttp.ClientWebSocketResponse,
        client: FspbxClient,
        context: CallContext,
    ) -> None:
        """Run any deferred call-changing tools, but only once the response is done
        AND the SIP audio buffer has drained. Falls back to a word-count delay only
        when the OpenAI session never emitted output_audio_buffer events for this
        response (defensive — those events should always fire for SIP-based realtime).
        """
        if not context.response_completed or context.audio_output_active:
            return

        if context.response_had_audio_transcript and not context.audio_buffer_observed:
            await asyncio.sleep(playback_delay_seconds(context.latest_assistant_transcript, minimum=0.5))

        if context.post_response_call_changing_tools:
            await self.run_scheduled_function_calls(ws, client, context)
            return

        if not context.pending_call_changing_tools:
            return

        # Silent-tool safety net: if the AI invoked end_call or a transfer tool in a
        # response that produced no audio (i.e. it never spoke to the caller in this
        # turn), defer the tool, acknowledge it with a placeholder, and ask the AI
        # for one brief spoken sentence first. After that response plays out, the
        # scheduled tool runs from post_response_call_changing_tools.
        if not context.response_had_audio_transcript:
            silent_end_calls = [
                call for call in context.pending_call_changing_tools
                if call["name"] == "end_call"
            ]
            if silent_end_calls:
                remaining_calls = [
                    call for call in context.pending_call_changing_tools
                    if call not in silent_end_calls
                ]
                context.pending_call_changing_tools.clear()
                context.pending_call_changing_tools.extend(remaining_calls)
                context.post_response_call_changing_tools.extend(silent_end_calls)

                for call in silent_end_calls:
                    await self.acknowledge_deferred_end_call(ws, context, call)

                await self.ws_send(ws, context, {
                    "type": "response.create",
                    "response": {
                        "instructions": (
                            "Say one brief, natural closing sentence to the caller in the language you have been using, then stop. "
                            "Do not call any tools in this response."
                        ),
                    },
                })
                return

            silent_transfer_calls = [
                call for call in context.pending_call_changing_tools
                if call["name"] in {"warm_transfer_call", "transfer_call"}
            ]
            if silent_transfer_calls:
                # Reject the silent call back to the model and prompt it to retry
                # WITH the announcement in the same response. We can't run the
                # transfer in the background and feed the AI a "now brief" prompt
                # later, because the unresolved-tool conversation state confuses
                # the model into not speaking. The natural happy path is: AI
                # speaks + calls the tool in one response. Steer the model back
                # to that path by erroring the silent call and asking it to
                # retry with the same arguments.
                remaining_calls = [
                    call for call in context.pending_call_changing_tools
                    if call not in silent_transfer_calls
                ]
                context.pending_call_changing_tools.clear()
                context.pending_call_changing_tools.extend(remaining_calls)

                for call in silent_transfer_calls:
                    await self.reject_silent_transfer_call(ws, context, call)

                await self.ws_send(ws, context, {
                    "type": "response.create",
                    "response": {
                        "instructions": (
                            "Your previous transfer tool invocation was rejected because you did not speak to the caller first. "
                            "In your next response you MUST: "
                            "(1) say ONE short sentence to the caller in the language you have been using, telling them you are connecting them now and asking them to hold; "
                            "(2) immediately after that sentence, invoke the same transfer tool again with the same arguments. "
                            "The spoken sentence AND the tool call must be in this SAME response."
                        ),
                    },
                })
                return

        await self.run_pending_function_calls(ws, client, context)

    async def audio_drain_fallback(
        self,
        ws: aiohttp.ClientWebSocketResponse,
        client: FspbxClient,
        context: CallContext,
    ) -> None:
        """Defensive timer: if output_audio_buffer.stopped never arrives, run pending
        tools anyway once we've waited a generous fraction longer than the heuristic
        playback length. This should rarely fire — it exists so a missed event can't
        strand the caller on hold forever.
        """
        max_wait = max(
            playback_delay_seconds(context.latest_assistant_transcript, minimum=0.5) + 4.0,
            5.0,
        )
        deadline = asyncio.get_running_loop().time() + max_wait

        while context.audio_output_active and asyncio.get_running_loop().time() < deadline:
            await asyncio.sleep(0.1)

        if context.audio_output_active:
            logger.warning(
                "output_audio_buffer.stopped not received within %.1fs for session %s; "
                "running deferred tools anyway.",
                max_wait,
                context.session_uuid,
            )
            context.audio_output_active = False

        await self.maybe_run_pending_tools(ws, client, context)

    async def run_pending_function_calls(
        self,
        ws: aiohttp.ClientWebSocketResponse,
        client: FspbxClient,
        context: CallContext,
    ) -> None:
        calls = context.pending_call_changing_tools[:]
        context.pending_call_changing_tools.clear()

        for call in calls:
            await self.run_function_call_and_respond(ws, client, context, call)

    async def acknowledge_deferred_end_call(
        self,
        ws: aiohttp.ClientWebSocketResponse,
        context: CallContext,
        call: dict[str, Any],
    ) -> None:
        await self.ws_send(ws, context, {
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

    async def reject_silent_transfer_call(
        self,
        ws: aiohttp.ClientWebSocketResponse,
        context: CallContext,
        call: dict[str, Any],
    ) -> None:
        await self.ws_send(ws, context, {
            "type": "conversation.item.create",
            "item": {
                "type": "function_call_output",
                "call_id": call["call_id"],
                "output": json.dumps({
                    "success": False,
                    "status": "announcement_required",
                    "error": (
                        "You invoked this transfer tool without first speaking to the caller, "
                        "which violates the tool's precondition. Retry by saying one short "
                        "announcement to the caller and then invoking the same transfer tool "
                        "with the same arguments in the SAME response."
                    ),
                }),
            },
        })

    async def run_scheduled_function_calls(
        self,
        ws: aiohttp.ClientWebSocketResponse,
        client: FspbxClient,
        context: CallContext,
    ) -> None:
        # Currently only end_call uses the post_response path. Transfer tools take
        # the reject-and-retry path instead — see reject_silent_transfer_call.
        calls = context.post_response_call_changing_tools[:]
        context.post_response_call_changing_tools.clear()

        for call in calls:
            await self.run_function_call(client, context, call["name"], call["arguments"])

    async def run_function_call_and_respond(
        self,
        ws: aiohttp.ClientWebSocketResponse,
        client: FspbxClient,
        context: CallContext,
        call: dict[str, Any],
    ) -> None:
        name = call["name"]

        # Before any bridge swap, stop OpenAI from emitting more audio so that the
        # tail of a response meant for the recipient is not piped to the caller (or
        # vice versa) once the bridge changes.
        if name in {"cancel_warm_transfer", "complete_warm_transfer"} and context.in_consult:
            await self.cancel_in_flight_response(ws, context)

        # An unhandled exception here would propagate up through monitor_call and
        # tear down the entire OpenAI Realtime session — the caller would just be
        # dropped mid-call. Catch any tool failure (network, FS PBX 5xx, FreeSWITCH
        # ESL errors, etc.), surface it to the AI as a structured failure, and let
        # the model recover (try a different route, apologize, take a message, etc.).
        try:
            result = await self.run_function_call(client, context, name, call["arguments"])
        except Exception as exc:
            logger.exception(
                "AI Receptionist tool %s failed for session %s.",
                name,
                context.session_uuid,
            )
            result = {
                "success": False,
                "error": str(exc),
                "tool_name": name,
                "recovery_hint": (
                    "The tool call failed. Briefly apologize to the caller in the language you "
                    "have been using and recover within the configured options. If resolve_route "
                    "returned no match, do NOT fall back to resolve_destination or invent a "
                    "destination — ask the caller a clarifying question to identify which "
                    "configured route fits, or take a message via send_route_email on the most "
                    "appropriate route. Only use resolve_destination + transfer_call (without "
                    "route_uuid) when the caller has explicitly named a specific extension, "
                    "queue, or person to reach. Do not end the call abruptly."
                ),
            }

        await self.ws_send(ws, context, {
            "type": "conversation.item.create",
            "item": {
                "type": "function_call_output",
                "call_id": call["call_id"],
                "output": json.dumps(result),
            },
        })

        # Update the OpenAI session persona + tool list to match the new audio path
        # so subsequent turns are correctly framed (caller vs recipient).
        await self.apply_session_changes_after_tool(ws, context, name, result)

        # Skip creating a new response when the OpenAI leg is about to be killed —
        # otherwise we'd burn compute and risk audio bleeding to the freshly bridged
        # caller↔recipient leg before the kill fires.
        if name == "complete_warm_transfer" and result.get("status") in {"completed", "already_completed"}:
            self.watch_warm_transfer_if_needed(ws, client, context, name, result)
            return

        response: dict[str, Any] = {}
        if result.get("response_instructions"):
            response["instructions"] = str(result["response_instructions"])

        if name == "warm_transfer_call" and result.get("status") == "recipient_connected":
            # Force audio output for the briefing — the model is otherwise free to
            # silently invoke complete_warm_transfer or cancel_warm_transfer and
            # the recipient would hear nothing. tool_choice="none" on the
            # per-response config blocks any tool call for this turn.
            response["tool_choice"] = "none"
            # Generate this response WITHOUT the prior caller-mode conversation as
            # input. response.input=[] tells OpenAI to use an empty input set; the
            # model then sees only the session-level (consult persona) plus the
            # per-response (briefing) instructions, with NO caller-side dialogue to
            # "naturally continue." Without this the model emits caller-mode lines
            # like "Still trying to reach support, please hold..." right before the
            # briefing, which the recipient hears through the bridged audio path.
            # (response.conversation stays at its default "auto" so the briefing
            # IS still recorded into the conversation afterwards — subsequent
            # recipient turns need it as context to reply appropriately.)
            response["input"] = []
            logger.info(
                "AI Receptionist warm transfer recipient_connected for session %s: "
                "route=%s, warm_transfer_uuid=%s; sending briefing response.create "
                "(consult delay %.1fs, tool_choice=none).",
                context.session_uuid,
                (result.get("route") or {}).get("name"),
                result.get("warm_transfer_uuid"),
                recipient_consult_response_delay_seconds(),
            )
            await asyncio.sleep(recipient_consult_response_delay_seconds())
            # Once the bridge swaps the OpenAI leg's input from caller to recipient,
            # server-side VAD typically fires on the recipient's first audio
            # (a "hello" or even mic noise) and auto-creates a response. That
            # auto-response collides with our briefing response.create with a
            # "conversation_already_has_active_response" error. Cancel any
            # in-flight response now so our briefing can take its place.
            await self.cancel_in_flight_response(ws, context)
        elif name == "cancel_warm_transfer":
            # Same protection for the apology back to the caller after a cancel —
            # we want the model to actually speak the apology, not silently chain
            # into another tool call.
            response["tool_choice"] = "none"

        await self.ws_send(ws, context, {
            "type": "response.create",
            **({"response": response} if response else {}),
        })

        self.watch_warm_transfer_if_needed(ws, client, context, name, result)

    async def apply_session_changes_after_tool(
        self,
        ws: aiohttp.ClientWebSocketResponse,
        context: CallContext,
        tool_name: str,
        result: dict[str, Any],
    ) -> None:
        if tool_name == "warm_transfer_call" and result.get("status") == "recipient_connected":
            await self.enter_consult_mode(
                ws,
                context,
                result.get("route") or {},
                str(result.get("handoff_summary") or ""),
            )
            return

        if tool_name == "cancel_warm_transfer" and context.in_consult:
            await self.restore_caller_session(ws, context)
            return

        if (
            tool_name == "complete_warm_transfer"
            and context.in_consult
            and result.get("status") in {"completed", "already_completed"}
        ):
            # The OpenAI leg is being killed — clear the flag without sending a
            # session.update (the WS is about to close).
            context.in_consult = False

    def watch_warm_transfer_if_needed(
        self,
        ws: aiohttp.ClientWebSocketResponse,
        client: FspbxClient,
        context: CallContext,
        tool_name: str,
        result: dict[str, Any],
    ) -> None:
        warm_transfer_uuid = str(result.get("warm_transfer_uuid") or "")
        if tool_name != "warm_transfer_call" or result.get("status") != "recipient_connected" or not warm_transfer_uuid:
            return

        watcher_key = f"{context.session_uuid}:{warm_transfer_uuid}"
        if watcher_key in self._warm_transfer_watchers:
            return

        self._warm_transfer_watchers.add(watcher_key)
        logger.info(
            "Watching AI Receptionist warm transfer %s for session %s.",
            warm_transfer_uuid,
            context.session_uuid,
        )
        asyncio.create_task(self.watch_warm_transfer_consult(
            ws,
            client,
            context,
            warm_transfer_uuid,
            watcher_key,
        ))

    async def watch_warm_transfer_consult(
        self,
        ws: aiohttp.ClientWebSocketResponse,
        client: FspbxClient,
        context: CallContext,
        warm_transfer_uuid: str,
        watcher_key: str,
    ) -> None:
        deadline = asyncio.get_running_loop().time() + 60

        try:
            while asyncio.get_running_loop().time() < deadline:
                await asyncio.sleep(2)

                try:
                    status = await client.check_warm_transfer(context.session_uuid, warm_transfer_uuid)
                except Exception as exc:
                    logger.warning(
                        "AI Receptionist warm transfer watchdog check failed for %s in session %s: %s",
                        warm_transfer_uuid,
                        context.session_uuid,
                        exc,
                    )
                    continue

                if status.get("active"):
                    continue

                terminal_status = str(status.get("status") or "")
                reason = str(status.get("reason") or "")

                # caller_gone is handled server-side (recipient leg is torn down there);
                # the watchdog just stops watching.
                if terminal_status == "caller_gone":
                    logger.info(
                        "AI Receptionist warm transfer %s for session %s ended: caller hung up.",
                        warm_transfer_uuid,
                        context.session_uuid,
                    )
                    return

                if not reason:
                    # Terminal but no failure reason (e.g. completed normally).
                    return

                logger.info(
                    "Cancelling AI Receptionist warm transfer %s for session %s after watchdog status %s.",
                    warm_transfer_uuid,
                    context.session_uuid,
                    terminal_status,
                )
                await self.cancel_warm_transfer_from_watchdog(ws, client, context, reason)
                return

            logger.info(
                "Cancelling AI Receptionist warm transfer %s for session %s after consult timeout.",
                warm_transfer_uuid,
                context.session_uuid,
            )
            await self.cancel_warm_transfer_from_watchdog(ws, client, context, "no_answer")
        except asyncio.CancelledError:
            raise
        except Exception:
            logger.exception(
                "AI Receptionist warm transfer watchdog failed for %s in session %s.",
                warm_transfer_uuid,
                context.session_uuid,
            )
        finally:
            self._warm_transfer_watchers.discard(watcher_key)

    async def cancel_warm_transfer_from_watchdog(
        self,
        ws: aiohttp.ClientWebSocketResponse,
        client: FspbxClient,
        context: CallContext,
        reason: str,
    ) -> None:
        # 1. Stop OpenAI from emitting more audio before the bridge swaps back.
        await self.cancel_in_flight_response(ws, context)

        # 2. Tell PHP to bridge the caller back to the OpenAI leg.
        try:
            result = await client.cancel_warm_transfer(context.session_uuid, reason)
        except Exception:
            logger.exception(
                "AI Receptionist watchdog cancel_warm_transfer failed for session %s.",
                context.session_uuid,
            )
            return

        # 3. Restore the caller-receptionist persona + tools before the AI speaks again.
        await self.restore_caller_session(ws, context)

        # 4. Ask the AI to apologize briefly to the original caller.
        instructions = result.get("response_instructions")
        if instructions and not ws.closed:
            await self.ws_send(ws, context, {
                "type": "response.create",
                "response": {
                    "instructions": str(instructions),
                    # Force audio output — don't let the model silently chain into
                    # another tool call instead of apologizing to the caller.
                    "tool_choice": "none",
                },
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
        context: CallContext,
        name: str,
        arguments: dict[str, Any],
    ) -> dict[str, Any]:
        session_uuid = context.session_uuid

        if name == "resolve_route":
            return await client.resolve_route(session_uuid, {"intent": arguments.get("intent", "")})

        if name == "resolve_destination":
            return await client.resolve_destination(session_uuid, {"intent": arguments.get("intent", "")})

        if name == "transfer_call":
            route_uuid = str(arguments.get("route_uuid") or "").strip()
            if route_uuid:
                # Configured-route cold transfer — server loads the route and uses
                # its stored destination_type/target. AI cannot inject arbitrary
                # values this way.
                return await client.transfer(session_uuid, {"route_uuid": route_uuid})

            destination_type = arguments.get("destination_type")
            target = arguments.get("target")
            if not destination_type or not target:
                return {
                    "success": False,
                    "error": (
                        "transfer_call requires either route_uuid (preferred, for a configured route) "
                        "or both destination_type and target (only when the caller explicitly named an "
                        "extension or queue and you looked it up via resolve_destination)."
                    ),
                    "tool_name": name,
                }

            destination = await client.resolve_destination(session_uuid, {
                "type": destination_type,
                "target": target,
            })
            return await client.transfer(session_uuid, {"destination": destination.get("destination") or destination})

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
                "transcript": "\n".join(context.transcript),
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
