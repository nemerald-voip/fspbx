from __future__ import annotations

from typing import Any

from livekit.agents import AgentSession
from livekit.plugins import openai

from agent.engines.base import EngineAdapter, FspbxReceptionistAgent, session_runtime_options
from agent.fspbx_client import FspbxClient


class OpenAIRealtimeAdapter(EngineAdapter):
    engine = "openai_realtime"

    async def create_session(self, ctx: Any, config: dict[str, Any], client: FspbxClient, session_uuid: str) -> AgentSession:
        provider_config = (config.get("settings") or {}).get("provider_config") or {}

        session = AgentSession(
            llm=openai.realtime.RealtimeModel(
                model=provider_config.get("openai_realtime_model", "gpt-realtime"),
                voice=provider_config.get("openai_voice", "marin"),
            ),
            **session_runtime_options(config),
        )

        await session.start(
            agent=FspbxReceptionistAgent(config, client, session_uuid),
            room=ctx.room,
        )

        return session
