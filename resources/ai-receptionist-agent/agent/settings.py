from __future__ import annotations

from dataclasses import dataclass
import os
from pathlib import Path

from dotenv import load_dotenv

_ENVIRONMENT_LOADED = False
_SETTINGS_CACHE: Settings | None = None


def load_environment(force: bool = False) -> None:
    global _ENVIRONMENT_LOADED, _SETTINGS_CACHE
    if _ENVIRONMENT_LOADED and not force:
        return

    project_root = Path(__file__).resolve().parents[3]
    agent_root = Path(__file__).resolve().parents[1]

    load_dotenv(project_root / ".env")
    load_dotenv(agent_root / ".env")
    _ENVIRONMENT_LOADED = True
    if force:
        _SETTINGS_CACHE = None


@dataclass(frozen=True)
class Settings:
    fspbx_base_url: str
    fspbx_agent_token: str
    health_host: str
    health_port: int

    @classmethod
    def from_env(cls, refresh: bool = False) -> "Settings":
        global _SETTINGS_CACHE
        if _SETTINGS_CACHE is not None and not refresh:
            return _SETTINGS_CACHE

        load_environment(force=refresh)
        base_url = os.getenv("FSPBX_BASE_URL") or os.getenv("APP_URL")
        token = os.getenv("FSPBX_AGENT_TOKEN") or os.getenv("AI_RECEPTIONIST_AGENT_TOKEN")

        if not base_url:
            raise RuntimeError("APP_URL or FSPBX_BASE_URL must be set for the AI Receptionist worker.")

        if not token:
            raise RuntimeError("AI_RECEPTIONIST_AGENT_TOKEN must be set for the AI Receptionist worker.")

        _SETTINGS_CACHE = cls(
            fspbx_base_url=base_url.rstrip("/"),
            fspbx_agent_token=token,
            health_host=os.getenv("AI_RECEPTIONIST_HEALTH_HOST", "127.0.0.1"),
            health_port=int(os.getenv("AI_RECEPTIONIST_HEALTH_PORT", "8097")),
        )
        return _SETTINGS_CACHE
