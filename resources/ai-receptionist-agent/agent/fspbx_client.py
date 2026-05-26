from __future__ import annotations

import asyncio
from typing import Any

import aiohttp


class FspbxClient:
    def __init__(
        self,
        base_url: str,
        token: str,
        *,
        connection_limit: int = 100,
        connection_limit_per_host: int = 50,
        keepalive_timeout: float = 30.0,
        request_timeout: float = 90.0,
    ) -> None:
        self.base_url = base_url.rstrip("/")
        self.headers = {
            "Authorization": f"Bearer {token}",
            "Accept": "application/json",
            "Content-Type": "application/json",
        }
        self.connection_limit = connection_limit
        self.connection_limit_per_host = connection_limit_per_host
        self.keepalive_timeout = keepalive_timeout
        self.request_timeout = request_timeout
        self._session: aiohttp.ClientSession | None = None
        self._loop: asyncio.AbstractEventLoop | None = None

    async def _request(self, method: str, path: str, payload: dict[str, Any] | None = None) -> dict[str, Any]:
        url = f"{self.base_url}{path}"
        session = await self._get_session()
        async with session.request(method, url, json=payload) as response:
            data = await response.json(content_type=None)
            if response.status >= 400:
                message = data.get("message") or data.get("messages") or data
                raise RuntimeError(f"FS PBX API error {response.status}: {message}")
            return data

    async def _get_session(self) -> aiohttp.ClientSession:
        loop = asyncio.get_running_loop()
        if self._session and not self._session.closed and self._loop is loop:
            return self._session

        if self._session and not self._session.closed:
            await self.close()

        connector = aiohttp.TCPConnector(
            limit=self.connection_limit,
            limit_per_host=self.connection_limit_per_host,
            keepalive_timeout=self.keepalive_timeout,
        )
        timeout = aiohttp.ClientTimeout(total=self.request_timeout)
        self._session = aiohttp.ClientSession(
            headers=self.headers,
            connector=connector,
            timeout=timeout,
        )
        self._loop = loop
        return self._session

    async def close(self) -> None:
        if self._session and not self._session.closed:
            await self._session.close()
        self._session = None
        self._loop = None

    @property
    def closed(self) -> bool:
        return self._session is not None and self._session.closed

    async def get_config(self, receptionist_uuid: str) -> dict[str, Any]:
        return await self._request("GET", f"/api/ai-receptionist-agent/receptionists/{receptionist_uuid}/config")

    async def start_session(self, receptionist_uuid: str, payload: dict[str, Any]) -> dict[str, Any]:
        return await self._request("POST", f"/api/ai-receptionist-agent/receptionists/{receptionist_uuid}/sessions", payload)

    async def resolve_destination(self, session_uuid: str, payload: dict[str, Any]) -> dict[str, Any]:
        return await self._request("POST", f"/api/ai-receptionist-agent/sessions/{session_uuid}/resolve-destination", payload)

    async def resolve_route(self, session_uuid: str, payload: dict[str, Any]) -> dict[str, Any]:
        return await self._request("POST", f"/api/ai-receptionist-agent/sessions/{session_uuid}/resolve-route", payload)

    async def transfer(self, session_uuid: str, destination: dict[str, Any]) -> dict[str, Any]:
        return await self._request("POST", f"/api/ai-receptionist-agent/sessions/{session_uuid}/transfer", {"destination": destination})

    async def warm_transfer(self, session_uuid: str, route_uuid: str, handoff_summary: str) -> dict[str, Any]:
        return await self._request("POST", f"/api/ai-receptionist-agent/sessions/{session_uuid}/warm-transfer", {
            "route_uuid": route_uuid,
            "handoff_summary": handoff_summary,
        })

    async def complete_warm_transfer(self, session_uuid: str, recipient_response: str) -> dict[str, Any]:
        return await self._request("POST", f"/api/ai-receptionist-agent/sessions/{session_uuid}/warm-transfer/complete", {
            "recipient_response": recipient_response,
        })

    async def cancel_warm_transfer(self, session_uuid: str, reason: str) -> dict[str, Any]:
        return await self._request("POST", f"/api/ai-receptionist-agent/sessions/{session_uuid}/warm-transfer/cancel", {
            "reason": reason,
        })

    async def send_route_email(self, session_uuid: str, payload: dict[str, Any]) -> dict[str, Any]:
        return await self._request("POST", f"/api/ai-receptionist-agent/sessions/{session_uuid}/route-email", payload)

    async def end_call(self, session_uuid: str, reason: str) -> dict[str, Any]:
        return await self._request("POST", f"/api/ai-receptionist-agent/sessions/{session_uuid}/end-call", {
            "reason": reason,
        })

    async def run_tool(self, session_uuid: str, tool_name: str, payload: dict[str, Any]) -> dict[str, Any]:
        return await self._request("POST", f"/api/ai-receptionist-agent/sessions/{session_uuid}/tools", {
            "tool_name": tool_name,
            "payload": payload,
        })

    async def end_session(self, session_uuid: str, payload: dict[str, Any]) -> dict[str, Any]:
        return await self._request("POST", f"/api/ai-receptionist-agent/sessions/{session_uuid}/end", payload)
