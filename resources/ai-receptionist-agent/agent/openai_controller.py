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
    "cold_transfer",
    "warm_transfer",
}

CONSULT_TOOL_NAMES = {"accept_transfer", "decline_transfer"}


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
        "ai_receptionist_mode": headers.get("x-fspbx-ai-receptionist-mode"),
        "warm_transfer_uuid": headers.get("x-fspbx-warm-transfer-uuid"),
        "sip_call_id": headers.get("call-id"),
    }


def receptionist_instructions(config: dict[str, Any]) -> str:
    return str(config.get("instructions") or config.get("instructions_preview") or "You are a helpful phone receptionist.")


def route_display(route: dict[str, Any]) -> str:
    name = str(route.get("name") or "Unnamed route")
    route_uuid = str(route.get("route_uuid") or "")
    return f"{name} ({route_uuid})" if route_uuid else name


def route_uuids_for_action(config: dict[str, Any], action: str) -> list[str]:
    routes = config.get("routes") or []
    uuids: list[str] = []

    for route in routes:
        if not isinstance(route, dict):
            continue

        route_uuid = str(route.get("route_uuid") or "").strip()
        if not route_uuid:
            continue

        if action == "cold_transfer":
            if route.get("action_type") == "transfer" and route.get("transfer_type") == "cold":
                uuids.append(route_uuid)
            continue

        if action == "warm_transfer":
            if route.get("action_type") == "transfer" and route.get("transfer_type") == "warm":
                uuids.append(route_uuid)
            continue

        if action == "send_email":
            # Email routes are normal message routes. Warm-transfer routes are
            # also valid after the warm transfer tool reports failure.
            if route.get("action_type") == "email" or (
                route.get("action_type") == "transfer" and route.get("transfer_type") == "warm"
            ):
                uuids.append(route_uuid)

    return uuids


def route_list_for_action(config: dict[str, Any], action: str) -> str:
    allowed = set(route_uuids_for_action(config, action))
    labels = [
        route_display(route)
        for route in (config.get("routes") or [])
        if isinstance(route, dict) and str(route.get("route_uuid") or "") in allowed
    ]

    return ", ".join(labels) if labels else "none"


def route_uuid_property(config: dict[str, Any], action: str) -> dict[str, Any]:
    prop: dict[str, Any] = {"type": "string"}
    uuids = route_uuids_for_action(config, action)

    if uuids:
        prop["enum"] = uuids

    return prop


def consult_persona_instructions(team: str, handoff_summary: str) -> str:
    summary = handoff_summary if handoff_summary else "The caller asked to be connected."
    team_label = team or "the requested team"
    return "\n".join([
        f"You are consulting with the transfer recipient for {team_label}.",
        "The original caller is parked on hold and cannot hear you. Only the recipient hears you.",
        f"Handoff context: {summary}",
        "Speak in the language you were using with the original caller, unless the recipient clearly prefers another.",
        "Only two outcomes are allowed:",
        "- If the recipient accepts (in any language), call accept_transfer with the recipient's exact spoken response in recipient_response.",
        "- If the recipient declines, is unavailable, or asks to call back later, call decline_transfer with reason \"declined\".",
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
    raw_delay = os.getenv("OPENAI_REALTIME_RECIPIENT_CONSULT_DELAY_SECONDS", "1.0")
    try:
        delay = float(raw_delay)
    except ValueError:
        delay = 1.0

    return min(max(delay, 0.0), 5.0)


def bridge_audio_drain_seconds() -> float:
    raw_delay = os.getenv("OPENAI_REALTIME_BRIDGE_DRAIN_SECONDS", "0.3")
    try:
        delay = float(raw_delay)
    except ValueError:
        delay = 0.3

    return min(max(delay, 0.0), 2.0)


def audio_drain_fallback_extra_seconds() -> float:
    raw_delay = os.getenv("OPENAI_REALTIME_AUDIO_DRAIN_FALLBACK_EXTRA_SECONDS", "1.0")
    try:
        delay = float(raw_delay)
    except ValueError:
        delay = 1.0

    return min(max(delay, 0.0), 4.0)


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
                "name": "cold_transfer",
                "description": (
                    "Cold transfer the caller to a configured cold-transfer route. "
                    f"Allowed routes: {route_list_for_action(config, 'cold_transfer')}. "
                    "If the caller said one of these route names, use that route immediately. "
                    "Before calling this tool, speak one short sentence telling the caller you are connecting them now."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "route_uuid": route_uuid_property(config, "cold_transfer"),
                    },
                    "required": ["route_uuid"],
                },
            },
            {
                "type": "function",
                "name": "warm_transfer",
                "description": (
                    "Start a warm transfer for a configured warm-transfer route. "
                    f"Allowed routes: {route_list_for_action(config, 'warm_transfer')}. "
                    "If the caller said one of these route names, use that route immediately. "
                    "Before calling this tool, tell the caller you are connecting them now and ask them to hold. "
                    "If the result says the transfer failed or was declined, collect a message and call send_email for the same route."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "route_uuid": route_uuid_property(config, "warm_transfer"),
                        "handoff_summary": {"type": "string"},
                    },
                    "required": ["route_uuid", "handoff_summary"],
                },
            },
            {
                "type": "function",
                "name": "send_email",
                "description": (
                    "Email a message collected from the caller to a configured email route, "
                    "or to a warm-transfer route only after that warm transfer failed. "
                    f"Allowed routes: {route_list_for_action(config, 'send_email')}. "
                    "Do not use this as a substitute for a clear cold_transfer or warm_transfer route match. "
                    "Collect caller name, callback number, and the message before calling."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "route_uuid": route_uuid_property(config, "send_email"),
                        "caller_name": {"type": "string"},
                        "caller_number": {"type": "string"},
                        "message": {"type": "string"},
                        "urgency": {"type": "string"},
                    },
                    "required": ["route_uuid", "message"],
                },
            },
        ],
        "tool_choice": "auto",
    }

    if not config.get("tool_access_enabled", True):
        payload.pop("tools")
        payload["tool_choice"] = "none"

    return payload


def consult_accept_payload(config: dict[str, Any]) -> dict[str, Any]:
    return {
        "type": "realtime",
        "model": config.get("model") or "gpt-realtime-2",
        "instructions": str(config.get("instructions") or "You are a private warm transfer consult agent."),
        "audio": {
            "input": {
                "transcription": {
                    "model": config.get("transcription_model") or os.getenv("OPENAI_REALTIME_TRANSCRIPTION_MODEL", "gpt-4o-mini-transcribe"),
                },
                "turn_detection": {
                    "type": "server_vad",
                    "create_response": False,
                    "interrupt_response": False,
                },
            },
            "output": {
                "voice": config.get("voice") or os.getenv("OPENAI_REALTIME_VOICE", "marin"),
            },
        },
        "tools": [
            {
                "type": "function",
                "name": "accept_transfer",
                "description": "Call only when the recipient clearly accepts taking the caller now. Include the recipient's exact spoken acceptance.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "recipient_response": {"type": "string"},
                    },
                    "required": ["recipient_response"],
                },
            },
            {
                "type": "function",
                "name": "decline_transfer",
                "description": "Call when the recipient declines, is unavailable, asks for a callback, or does not clearly accept the call.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "reason": {"type": "string"},
                    },
                },
            },
        ],
        "tool_choice": "auto",
    }


@dataclass
class CallContext:
    config: dict[str, Any]
    session_uuid: str
    mode: str
    transcript: list[str]
    ws_lock: asyncio.Lock
    base_instructions: str
    base_tools: list[dict[str, Any]] | None
    base_tool_choice: str
    warm_transfer_uuid: str | None = None
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
    consult_briefing_requested: bool = False
    pending_initial_message: str | None = None
    # output_audio_buffer.{started,stopped} let us know when the SIP playback buffer
    # has actually drained, which is the only reliable signal for "the caller heard
    # everything we just generated". Word-count heuristics on transcript.done get
    # the audio length wrong and cause the bridge swap to clip the tail.
    audio_output_active: bool = False
    response_had_audio_transcript: bool = False
    audio_buffer_observed: bool = False
    auto_response_disabled: bool = False


class RealtimeCallController:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self._warm_transfer_watchers: set[str] = set()

    async def accept_incoming_call(self, payload: dict[str, Any]) -> str:
        call_id = str(payload.get("call_id") or "")
        if not call_id:
            raise RuntimeError("OpenAI realtime call missing call_id.")

        headers = sip_header_map(payload.get("sip_headers") or [])
        metadata = metadata_from_headers(headers)
        receptionist_uuid = metadata.get("ai_receptionist_uuid")
        mode = metadata.get("ai_receptionist_mode") or "caller"
        warm_transfer_uuid = metadata.get("warm_transfer_uuid")
        accept_config = quick_accept_payload()

        if mode == "consult" and warm_transfer_uuid:
            client = FspbxClient(self.settings.fspbx_base_url, self.settings.fspbx_agent_token)
            try:
                accept_config = consult_accept_payload(await client.get_consult_config(warm_transfer_uuid))
            finally:
                await client.close()
        elif receptionist_uuid:
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
        mode = metadata.get("ai_receptionist_mode") or "caller"
        warm_transfer_uuid = metadata.get("warm_transfer_uuid")

        if not call_id or (mode == "caller" and not receptionist_uuid) or (mode == "consult" and not warm_transfer_uuid):
            logger.warning("OpenAI realtime call missing required identifiers for mode %s.", mode)
            return

        client = FspbxClient(self.settings.fspbx_base_url, self.settings.fspbx_agent_token)
        session_uuid: str | None = None
        transcript: list[str] = []

        try:
            async with aiohttp.ClientSession(headers=openai_realtime_headers()) as http:
                if mode == "consult" and warm_transfer_uuid:
                    config = await client.get_consult_config(warm_transfer_uuid)
                    await client.start_consult(warm_transfer_uuid, {
                        "openai_call_id": call_id,
                        "sip_call_id": metadata.get("sip_call_id"),
                        "freeswitch_uuid": metadata.get("ai_receptionist_freeswitch_uuid"),
                        "metadata": {
                            **payload,
                            "sip_header_map": headers,
                        },
                    })
                    session_uuid = str(config.get("session_uuid") or warm_transfer_uuid)
                    await self.monitor_call(http, call_id, client, session_uuid, config, transcript, mode="consult", warm_transfer_uuid=warm_transfer_uuid)
                else:
                    config = await client.get_config(str(receptionist_uuid))
                    session_response = await client.start_session(str(receptionist_uuid), {
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
                    await self.monitor_call(http, call_id, client, session_uuid, config, transcript, mode="caller")

            if session_uuid and mode == "caller":
                await client.end_session(session_uuid, {
                    "status": "completed",
                    "transcript": "\n".join(transcript),
                })
        except Exception as exc:
            logger.exception("OpenAI realtime call %s failed", call_id)
            if session_uuid and mode == "caller":
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
        context.consult_briefing_requested = False
        context.auto_response_disabled = False

    async def set_caller_auto_response(
        self,
        ws: aiohttp.ClientWebSocketResponse,
        context: CallContext,
        enabled: bool,
    ) -> None:
        if context.mode != "caller" or context.in_consult or context.auto_response_disabled == (not enabled):
            return

        audio_config: dict[str, Any] = (
            {k: (dict(v) if isinstance(v, dict) else v) for k, v in (context.base_audio or {}).items()}
        )
        audio_input = dict(audio_config.get("input") or {})
        audio_input["turn_detection"] = {
            "type": "server_vad",
            "create_response": enabled,
            "interrupt_response": enabled,
        }
        audio_config["input"] = audio_input

        await self.ws_send(ws, context, {
            "type": "session.update",
            "session": {
                "type": "realtime",
                "audio": audio_config,
            },
        })
        context.auto_response_disabled = not enabled

    async def send_consult_briefing(
        self,
        ws: aiohttp.ClientWebSocketResponse,
        context: CallContext,
    ) -> None:
        if (
            context.mode != "consult"
            or not context.pending_initial_message
            or context.consult_briefing_requested
            or not context.response_completed
            or ws.closed
        ):
            return

        context.consult_briefing_requested = True
        await asyncio.sleep(recipient_consult_response_delay_seconds())
        await self.ws_send(ws, context, {
            "type": "response.create",
            "response": {
                "instructions": context.pending_initial_message,
                "tool_choice": "none",
            },
        })

    async def monitor_call(
        self,
        http: aiohttp.ClientSession,
        call_id: str,
        client: FspbxClient,
        session_uuid: str,
        config: dict[str, Any],
        transcript: list[str],
        *,
        mode: str = "caller",
        warm_transfer_uuid: str | None = None,
    ) -> None:
        accept = consult_accept_payload(config) if mode == "consult" else accept_payload(config)
        context = CallContext(
            config=config,
            session_uuid=session_uuid,
            mode=mode,
            transcript=transcript,
            ws_lock=asyncio.Lock(),
            base_instructions=str(accept.get("instructions") or ""),
            base_tools=accept.get("tools"),
            base_tool_choice=str(accept.get("tool_choice") or "auto"),
            warm_transfer_uuid=warm_transfer_uuid,
            base_audio=accept.get("audio") or {},
        )

        url = f"wss://api.openai.com/v1/realtime?call_id={call_id}"

        for attempt in range(1, 6):
            try:
                async with http.ws_connect(url, headers=openai_realtime_headers()) as ws:
                    initial_message = config.get("initial_message")
                    if initial_message and mode == "consult":
                        context.pending_initial_message = str(initial_message)
                        logger.info(
                            "AI Receptionist consult briefing queued for session %s; waiting for recipient speech.",
                            context.session_uuid,
                        )
                    elif initial_message:
                        await asyncio.sleep(
                            initial_response_delay_seconds()
                        )
                        response: dict[str, Any] = {"instructions": initial_message}

                        await self.ws_send(ws, context, {
                            "type": "response.create",
                            "response": response,
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
            # In consult mode we disable VAD auto-response creation. The first
            # recipient speech boundary proves the human side is present, so only
            # then do we play the private briefing. After the briefing, subsequent
            # recipient turns manually trigger response.create for accept/decline.
            if (
                context.mode == "consult"
                and context.pending_initial_message
                and not context.consult_briefing_requested
                and context.response_completed
                and not ws.closed
            ):
                logger.info(
                    "AI Receptionist consult: recipient spoke for session %s; sending queued briefing.",
                    context.session_uuid,
                )
                await self.send_consult_briefing(ws, context)
                return

            if (
                context.mode == "consult"
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
            speaker = "Recipient" if context.mode == "consult" else "Caller"
            context.transcript.append(f"{speaker}: " + str(event.get("transcript") or ""))
            return

        if event_type in {"response.output_audio_transcript.done", "response.audio_transcript.done"}:
            assistant_transcript = str(event.get("transcript") or "")
            context.latest_assistant_transcript = assistant_transcript
            context.assistant_transcript_version += 1
            speaker = "Consult Agent" if context.mode == "consult" else "Assistant"
            context.transcript.append(f"{speaker}: " + assistant_transcript)
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
            if context.mode == "consult" and context.consult_briefing_requested and not context.consult_briefing_done:
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

        if self.should_defer_call_changing_tool(context, str(call["name"] or "")):
            logger.info(
                "Deferring AI Receptionist call-changing tool %s for session %s.",
                call["name"],
                context.session_uuid,
            )
            await self.set_caller_auto_response(ws, context, False)
            call["_queued_after_transcript_version"] = context.assistant_transcript_version
            context.pending_call_changing_tools.append(call)

            if context.response_completed and not context.audio_output_active:
                await self.maybe_run_pending_tools(ws, client, context)

            return

        await self.run_function_call_and_respond(ws, client, context, call)

    def should_defer_call_changing_tool(self, context: CallContext, tool_name: str) -> bool:
        if tool_name in CALL_CHANGING_TOOLS:
            return True

        return context.mode == "consult" and tool_name in CONSULT_TOOL_NAMES

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
                if call["name"] in {"warm_transfer", "cold_transfer"}
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

            silent_consult_decision_calls = [
                call for call in context.pending_call_changing_tools
                if context.mode == "consult" and call["name"] in CONSULT_TOOL_NAMES
            ]
            if silent_consult_decision_calls:
                await self.run_pending_function_calls(ws, client, context)
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
            playback_delay_seconds(context.latest_assistant_transcript, minimum=0.5) + audio_drain_fallback_extra_seconds(),
            2.0,
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
        if (
            name in {"accept_transfer", "decline_transfer", "cancel_warm_transfer", "complete_warm_transfer"}
            and (context.in_consult or context.mode == "consult")
        ):
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
                    "have been using and recover within the configured options. Do not invent a "
                    "destination or route_uuid. Ask one clarifying question to identify which "
                    "configured route fits, or take a message via send_email on the most "
                    "appropriate configured route. Do not end the call abruptly."
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
        if (
            name in {"cold_transfer", "accept_transfer", "decline_transfer"}
            and result.get("success")
        ) or (
            name == "warm_transfer"
            and result.get("status") == "completed"
        ) or (
            name == "complete_warm_transfer" and result.get("status") in {"completed", "already_completed"}
        ):
            self.watch_warm_transfer_if_needed(ws, client, context, name, result)
            return

        response: dict[str, Any] = {}
        if result.get("response_instructions"):
            response["instructions"] = str(result["response_instructions"])

        if name == "send_email" and result.get("success"):
            await self.set_caller_auto_response(ws, context, False)
            context.post_response_call_changing_tools.append({
                "name": "end_call",
                "arguments": {
                    "reason": "message_sent",
                    "status": "completed",
                },
            })
            response["instructions"] = (
                "Tell the caller in one brief sentence that the message has been sent "
                "and the team will follow up. Then say goodbye and stop. Do not ask "
                "another question. Do not call any tools."
            )
            response["tool_choice"] = "none"

        if name != "send_email" and context.auto_response_disabled:
            await self.set_caller_auto_response(ws, context, True)

        if name in {"decline_transfer"}:
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
        return

    def watch_warm_transfer_if_needed(
        self,
        ws: aiohttp.ClientWebSocketResponse,
        client: FspbxClient,
        context: CallContext,
        tool_name: str,
        result: dict[str, Any],
    ) -> None:
        # Two-agent warm transfer is synchronously resolved by the Laravel
        # warm_transfer tool call, so the caller-side Realtime controller no longer
        # needs a consult watchdog.
        return

        warm_transfer_uuid = str(result.get("warm_transfer_uuid") or "")
        if not warm_transfer_uuid:
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

        if name == "cold_transfer":
            return await client.cold_transfer(
                session_uuid,
                str(arguments.get("route_uuid") or ""),
            )

        if name == "warm_transfer":
            return await client.warm_transfer(
                session_uuid,
                str(arguments.get("route_uuid") or ""),
                str(arguments.get("handoff_summary") or ""),
            )

        if name == "send_email":
            return await client.send_email(session_uuid, {
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

        if name == "accept_transfer":
            if not context.warm_transfer_uuid:
                return {"success": False, "error": "Missing warm transfer UUID."}
            return await client.accept_transfer(
                context.warm_transfer_uuid,
                str(arguments.get("recipient_response") or ""),
                "\n".join(context.transcript),
            )

        if name == "decline_transfer":
            if not context.warm_transfer_uuid:
                return {"success": False, "error": "Missing warm transfer UUID."}
            return await client.decline_transfer(
                context.warm_transfer_uuid,
                str(arguments.get("reason") or "declined"),
                "\n".join(context.transcript),
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
