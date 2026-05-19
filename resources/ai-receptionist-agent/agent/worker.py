from __future__ import annotations

import asyncio
import json
import logging
import os
import threading
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen

from livekit.agents import AgentServer, JobContext, JobProcess, cli
from livekit.plugins import silero

from agent.engines.assemblyai_agent import AssemblyAIAgentAdapter
from agent.engines.openai_realtime import OpenAIRealtimeAdapter
from agent.engines.standard_pipeline import StandardPipelineAdapter
from agent.fspbx_client import FspbxClient
from agent.health import main as health_main
from agent.settings import Settings, load_environment

load_environment()

logger = logging.getLogger("fspbx-ai-receptionist")
logging.basicConfig(level=logging.INFO)

server = AgentServer()
health_started = False


def start_health_server() -> None:
    global health_started
    if health_started:
        return

    health_started = True
    threading.Thread(target=health_main, name="ai-receptionist-health", daemon=True).start()


def prewarm(proc: JobProcess) -> None:
    proc.userdata["vad"] = silero.VAD.load()
    start_health_server()


server.setup_fnc = prewarm


ADAPTERS = {
    StandardPipelineAdapter.engine: StandardPipelineAdapter(),
    OpenAIRealtimeAdapter.engine: OpenAIRealtimeAdapter(),
    AssemblyAIAgentAdapter.engine: AssemblyAIAgentAdapter(),
}

HEADER_MAP = {
    "x-fspbx-domain-uuid": "domain_uuid",
    "x-fspbx-ai-receptionist-uuid": "ai_receptionist_uuid",
    "x-fspbx-freeswitch-uuid": "ai_receptionist_freeswitch_uuid",
    "x-fspbx-caller-id-name": "ai_receptionist_caller_id_name",
    "x-fspbx-caller-id-number": "ai_receptionist_caller_id_number",
    "x-fspbx-destination-number": "ai_receptionist_destination_number",
}


def bootstrap_livekit_environment() -> None:
    required = ("LIVEKIT_URL", "LIVEKIT_API_KEY", "LIVEKIT_API_SECRET")
    if all(os.getenv(key) for key in required):
        return

    settings = Settings.from_env()
    request = Request(
        f"{settings.fspbx_base_url}/api/ai-receptionist-agent/bootstrap",
        headers={
            "Authorization": f"Bearer {settings.fspbx_agent_token}",
            "Accept": "application/json",
        },
    )

    try:
        with urlopen(request, timeout=15) as response:
            payload = json.loads(response.read().decode())
    except HTTPError as exc:
        detail = exc.read().decode(errors="replace")
        raise RuntimeError(f"FS PBX AI Receptionist bootstrap failed with HTTP {exc.code}: {detail}") from exc
    except URLError as exc:
        raise RuntimeError(f"FS PBX AI Receptionist bootstrap failed: {exc.reason}") from exc

    mappings = {
        "LIVEKIT_URL": payload.get("livekit_url"),
        "LIVEKIT_API_KEY": payload.get("livekit_api_key"),
        "LIVEKIT_API_SECRET": payload.get("livekit_api_secret"),
    }

    for key, value in mappings.items():
        if value and not os.getenv(key):
            os.environ[key] = str(value)

    missing = [key for key in required if not os.getenv(key)]
    if missing:
        raise RuntimeError(
            "AI Receptionist LiveKit settings are incomplete. Missing: " + ", ".join(missing)
        )


def job_metadata(ctx: JobContext) -> dict[str, Any]:
    raw = getattr(ctx.job, "metadata", None) or getattr(ctx.room, "metadata", None) or "{}"
    try:
        return json.loads(raw) if isinstance(raw, str) else dict(raw or {})
    except (TypeError, ValueError):
        logger.warning("Invalid LiveKit job metadata: %s", raw)
        return {}


def room_header_metadata(ctx: JobContext) -> dict[str, Any]:
    metadata: dict[str, Any] = {}
    participants = getattr(ctx.room, "remote_participants", {}) or {}

    for participant in participants.values():
        attributes = getattr(participant, "attributes", {}) or {}
        for key, value in attributes.items():
            normalized = key.lower()
            for prefix in ("sip.h.", "sip.header.", "sip.headers."):
                if normalized.startswith(prefix):
                    normalized = normalized[len(prefix):]
                    break

            mapped_key = HEADER_MAP.get(normalized)
            if mapped_key and value:
                metadata[mapped_key] = value

    return metadata


def _positive_float(value: Any, default: float | None = None) -> float | None:
    try:
        parsed = float(value)
    except (TypeError, ValueError):
        return default

    return parsed if parsed > 0 else default


def install_idle_timeout(
    session: Any,
    config: dict[str, Any],
    client: FspbxClient,
    session_uuid: str,
) -> asyncio.Event | None:
    timeout = _positive_float(config.get("user_idle_timeout_seconds"))
    if timeout is None:
        return None

    done = asyncio.Event()
    idle_task: asyncio.Task[None] | None = None

    def cancel_idle_timer() -> None:
        nonlocal idle_task
        if idle_task and not idle_task.done():
            idle_task.cancel()
        idle_task = None

    async def idle_timeout_task() -> None:
        try:
            await asyncio.sleep(timeout)
            logger.info("AI receptionist session %s stopped after %.1f seconds of caller silence", session_uuid, timeout)
            await client.end_session(session_uuid, {
                "status": "idle_timeout",
                "error_message": f"Caller was silent for {timeout:.0f} seconds.",
            })
            await session.aclose()
            done.set()
        except asyncio.CancelledError:
            raise
        except Exception:
            logger.exception("Failed while stopping idle AI receptionist session %s", session_uuid)
            done.set()

    def reset_idle_timer() -> None:
        nonlocal idle_task
        cancel_idle_timer()
        idle_task = asyncio.create_task(idle_timeout_task())

    def check_in() -> None:
        message = config.get("silence_checkin_message") or "Are you still there?"
        try:
            session.generate_reply(instructions=message)
        except Exception:
            logger.exception("Failed to generate idle check-in for AI receptionist session %s", session_uuid)

    @session.on("user_state_changed")
    def on_user_state_changed(event: Any) -> None:
        if event.new_state == "speaking":
            cancel_idle_timer()
        elif event.new_state == "listening":
            reset_idle_timer()
        elif event.new_state == "away":
            check_in()

    @session.on("close")
    def on_close(*_: Any) -> None:
        cancel_idle_timer()
        done.set()

    reset_idle_timer()
    return done


@server.rtc_session(agent_name=os.getenv("AI_RECEPTIONIST_AGENT_NAME", "ai-receptionist"))
async def entrypoint(ctx: JobContext) -> None:
    ctx.log_context_fields = {"room": ctx.room.name}
    settings = Settings.from_env()
    metadata = job_metadata(ctx)
    connected = False
    receptionist_uuid = metadata.get("ai_receptionist_uuid")

    if not receptionist_uuid:
        await ctx.connect()
        connected = True
        metadata.update(room_header_metadata(ctx))
        receptionist_uuid = metadata.get("ai_receptionist_uuid")

    if not receptionist_uuid:
        raise RuntimeError("LiveKit metadata or SIP headers must include ai_receptionist_uuid.")

    client = FspbxClient(settings.fspbx_base_url, settings.fspbx_agent_token)
    config = await client.get_config(receptionist_uuid)
    session_response = await client.start_session(receptionist_uuid, {
        "freeswitch_uuid": metadata.get("ai_receptionist_freeswitch_uuid") or metadata.get("freeswitch_uuid"),
        "livekit_room": ctx.room.name,
        "caller_id_name": metadata.get("ai_receptionist_caller_id_name"),
        "caller_id_number": metadata.get("ai_receptionist_caller_id_number"),
        "destination_number": metadata.get("ai_receptionist_destination_number"),
        "metadata": metadata,
    })

    session_uuid = session_response["session_uuid"]
    adapter = ADAPTERS.get(config["engine"])
    if not adapter:
        await client.end_session(session_uuid, {
            "status": "failed",
            "error_message": f"Unsupported engine {config['engine']}",
        })
        raise RuntimeError(f"Unsupported engine {config['engine']}")

    livekit_session = None

    try:
        logger.info("Starting AI receptionist session %s with %s", session_uuid, config["engine"])
        livekit_session = await adapter.create_session(ctx, config, client, session_uuid)
        idle_done = install_idle_timeout(livekit_session, config, client, session_uuid)
        if not connected:
            await ctx.connect()
        if idle_done:
            await idle_done.wait()
        else:
            await asyncio.Future()
    except asyncio.CancelledError:
        await client.end_session(session_uuid, {"status": "completed"})
        raise
    except Exception as exc:
        await client.end_session(session_uuid, {
            "status": "failed",
            "error_message": str(exc),
        })
        raise
    finally:
        if livekit_session:
            await livekit_session.aclose()


if __name__ == "__main__":
    bootstrap_livekit_environment()
    cli.run_app(server)
