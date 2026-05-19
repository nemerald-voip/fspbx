from __future__ import annotations

from typing import Any

from livekit.agents import Agent, RunContext, TurnHandlingOptions, function_tool

from agent.fspbx_client import FspbxClient


class FspbxReceptionistAgent(Agent):
    def __init__(self, config: dict[str, Any], client: FspbxClient, session_uuid: str) -> None:
        self.config = config
        self.client = client
        self.session_uuid = session_uuid
        instructions = "\n\n".join(filter(None, [
            config.get("system_prompt"),
            "You may transfer calls only by using the transfer_call tool.",
            "Use run_http_tool only for tools included in the current FS PBX configuration.",
        ]))
        super().__init__(instructions=instructions or "You are a helpful phone receptionist.")

    async def on_enter(self) -> None:
        initial_message = self.config.get("initial_message")
        if initial_message:
            self.session.generate_reply(instructions=initial_message)

    @function_tool
    async def resolve_destination(self, context: RunContext, intent: str) -> dict[str, Any]:
        """Find a PBX destination by caller intent, name, or extension."""
        return await self.client.resolve_destination(self.session_uuid, {"intent": intent})

    @function_tool
    async def transfer_call(self, context: RunContext, destination_type: str, target: str) -> dict[str, Any]:
        """Transfer the original FreeSWITCH caller A-leg to an approved PBX destination."""
        destination = await self.client.resolve_destination(self.session_uuid, {
            "type": destination_type,
            "target": target,
        })
        result = await self.client.transfer(self.session_uuid, destination)
        if result.get("success"):
            await self.session.aclose()
        return result

    @function_tool
    async def run_http_tool(self, context: RunContext, tool_name: str, payload: dict[str, Any]) -> dict[str, Any]:
        """Run a domain-approved generic HTTP tool through FS PBX."""
        return await self.client.run_tool(self.session_uuid, tool_name, payload)


class EngineAdapter:
    engine = ""

    async def create_session(self, ctx: Any, config: dict[str, Any], client: FspbxClient, session_uuid: str) -> Any:
        raise NotImplementedError


def session_runtime_options(config: dict[str, Any]) -> dict[str, Any]:
    checkin_seconds = _positive_float(config.get("user_silence_checkin_seconds"), 15.0)
    min_interruption_duration = _positive_float(config.get("min_interruption_duration"), 0.5)

    return {
        "user_away_timeout": checkin_seconds,
        "turn_handling": TurnHandlingOptions(
            interruption={
                "enabled": _bool_setting(config.get("allow_interruptions"), True),
                "min_duration": min_interruption_duration,
            },
        ),
    }


def _positive_float(value: Any, default: float) -> float:
    try:
        parsed = float(value)
    except (TypeError, ValueError):
        return default

    return parsed if parsed > 0 else default


def _bool_setting(value: Any, default: bool) -> bool:
    if value is None:
        return default
    if isinstance(value, bool):
        return value
    return str(value).strip().lower() in {"1", "true", "yes", "on"}
