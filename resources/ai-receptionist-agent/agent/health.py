from __future__ import annotations

import json
from http.server import BaseHTTPRequestHandler, HTTPServer

from agent.settings import Settings


class HealthHandler(BaseHTTPRequestHandler):
    def do_GET(self) -> None:
        body = json.dumps({"status": "ok", "service": "ai-receptionist-agent"}).encode()
        self.send_response(200)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, format: str, *args: object) -> None:
        return


def main() -> None:
    settings = Settings.from_env()
    HTTPServer((settings.health_host, settings.health_port), HealthHandler).serve_forever()


if __name__ == "__main__":
    main()
