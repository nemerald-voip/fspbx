# FS PBX AI Receptionist Agent

This controller accepts OpenAI Realtime SIP calls handed to FS PBX by the
Laravel OpenAI webhook route. Incoming OpenAI webhooks are stored through
Spatie webhook-client in `webhook_calls` before the local controller is called.
FreeSWITCH keeps the original caller leg, while
the controller keeps the Realtime WebSocket open for transcripts, tools, and
PBX transfers.

## Runtime

The worker loads the main FS PBX `.env` file. It does not need a separate
Python `.env`.

The only worker-specific Laravel secret required in the main FS PBX `.env` is:

```dotenv
AI_RECEPTIONIST_AGENT_TOKEN=
AI_RECEPTIONIST_CONTROLLER_URL=http://127.0.0.1:8097/calls
AI_RECEPTIONIST_HEALTH_HOST=127.0.0.1
AI_RECEPTIONIST_HEALTH_PORT=8097
OPENAI_API_KEY=
OPENAI_WEBHOOK_SECRET=
```

`APP_URL` is used as the FS PBX API base URL. `FSPBX_BASE_URL` and
`FSPBX_AGENT_TOKEN` are optional overrides for unusual deployments.

OpenAI project ID, SIP bridge target, model, and voice choices are managed in
FS PBX System Settings. The controller fetches per-receptionist configuration
from Laravel for each call. The dashboard controls the local Supervisor service
on this server.

## Install

```bash
cd /var/www/fspbx/resources/ai-receptionist-agent
sudo mkdir -p /opt/fspbx/ai-receptionist-agent
sudo python3 -m venv /opt/fspbx/ai-receptionist-agent/.venv
sudo /opt/fspbx/ai-receptionist-agent/.venv/bin/pip install -r requirements.txt
/opt/fspbx/ai-receptionist-agent/.venv/bin/python -B -m agent.openai_controller
```

The source lives in the repository under `resources/ai-receptionist-agent`.
The Python virtual environment is installed under `/opt/fspbx` so dependency
files do not become untracked repository files.

The worker also starts a small local health endpoint. Check it with:

```bash
curl http://127.0.0.1:8097/health
```

The supervisor template is stored at `install/ai-receptionist-agent.conf`.
After it is installed, Supervisor starts the local controller automatically on
boot.
