from __future__ import annotations

from typing import Any

import aiohttp


class FspbxClient:
    def __init__(self, base_url: str, token: str) -> None:
        self.base_url = base_url.rstrip("/")
        self.headers = {
            "Authorization": f"Bearer {token}",
            "Accept": "application/json",
            "Content-Type": "application/json",
        }

    async def _request(self, method: str, path: str, payload: dict[str, Any] | None = None) -> dict[str, Any]:
        url = f"{self.base_url}{path}"
        async with aiohttp.ClientSession(headers=self.headers) as session:
            async with session.request(method, url, json=payload) as response:
                data = await response.json(content_type=None)
                if response.status >= 400:
                    message = data.get("message") or data.get("messages") or data
                    raise RuntimeError(f"FS PBX API error {response.status}: {message}")
                return data

    async def get_config(self, receptionist_uuid: str) -> dict[str, Any]:
        return await self._request("GET", f"/api/ai-receptionist-agent/receptionists/{receptionist_uuid}/config")

    async def start_session(self, receptionist_uuid: str, payload: dict[str, Any]) -> dict[str, Any]:
        return await self._request("POST", f"/api/ai-receptionist-agent/receptionists/{receptionist_uuid}/sessions", payload)

    async def resolve_destination(self, session_uuid: str, payload: dict[str, Any]) -> dict[str, Any]:
        return await self._request("POST", f"/api/ai-receptionist-agent/sessions/{session_uuid}/resolve-destination", payload)

    async def transfer(self, session_uuid: str, destination: dict[str, Any]) -> dict[str, Any]:
        return await self._request("POST", f"/api/ai-receptionist-agent/sessions/{session_uuid}/transfer", {"destination": destination})

    async def run_tool(self, session_uuid: str, tool_name: str, payload: dict[str, Any]) -> dict[str, Any]:
        return await self._request("POST", f"/api/ai-receptionist-agent/sessions/{session_uuid}/tools", {
            "tool_name": tool_name,
            "payload": payload,
        })

    async def end_session(self, session_uuid: str, payload: dict[str, Any]) -> dict[str, Any]:
        return await self._request("POST", f"/api/ai-receptionist-agent/sessions/{session_uuid}/end", payload)
