from __future__ import annotations

from typing import Any

from livekit.agents import AgentSession, inference

from agent.engines.base import EngineAdapter, FspbxReceptionistAgent, session_runtime_options
from agent.fspbx_client import FspbxClient


class StandardPipelineAdapter(EngineAdapter):
    engine = "standard_pipeline"

    async def create_session(self, ctx: Any, config: dict[str, Any], client: FspbxClient, session_uuid: str) -> AgentSession:
        provider_config = (config.get("settings") or {}).get("provider_config") or {}
        deepgram_model = inference_model_id(
            provider_config.get("deepgram_model", "deepgram/flux-general"),
            "deepgram",
        )
        elevenlabs_model = inference_model_id(
            provider_config.get("elevenlabs_model", "elevenlabs/eleven_flash_v2_5"),
            "elevenlabs",
        )
        elevenlabs_voice = str(provider_config.get("elevenlabs_voice_id") or "").strip()
        if not elevenlabs_voice:
            raise RuntimeError("ElevenLabs Voice ID is required for the selected AI Receptionist pipeline.")

        openai_model = inference_model_id(
            provider_config.get("openai_model", "openai/gpt-4o-mini"),
            "openai",
        )

        session = AgentSession(
            stt=inference.STT(
                model=deepgram_model,
                language=provider_config.get("deepgram_language", "en"),
            ),
            llm=inference.LLM(model=openai_model),
            tts=inference.TTS(
                model=elevenlabs_model,
                voice=elevenlabs_voice,
                language=provider_config.get("elevenlabs_language", "en"),
            ),
            vad=ctx.proc.userdata["vad"],
            **session_runtime_options(config),
        )

        await session.start(
            agent=FspbxReceptionistAgent(config, client, session_uuid),
            room=ctx.room,
        )

        return session


def inference_model_id(value: str, provider: str) -> str:
    value = str(value or "").strip()
    if not value:
        return value
    if "/" in value:
        return value
    return f"{provider}/{value}"
