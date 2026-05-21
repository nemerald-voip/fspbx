# FS PBX AI Receptionist Agent

This worker joins LiveKit rooms created by FreeSWITCH SIP bridging and keeps
all PBX actions behind Laravel.

## Runtime

The worker loads the main FS PBX `.env` file. It does not need a separate
Python `.env`.

The only worker-specific Laravel secret required in the main FS PBX `.env` is:

```dotenv
AI_RECEPTIONIST_AGENT_TOKEN=
AI_RECEPTIONIST_AGENT_NAME=ai-receptionist
AI_RECEPTIONIST_HEALTH_HOST=127.0.0.1
AI_RECEPTIONIST_HEALTH_PORT=8097
AI_RECEPTIONIST_IDLE_PROCESSES=1
```

`APP_URL` is used as the FS PBX API base URL. `FSPBX_BASE_URL` and
`FSPBX_AGENT_TOKEN` are optional overrides for unusual deployments.

LiveKit URL, API key, API secret, engine selection, and model choices are
managed in FS PBX System Settings. The worker fetches those values from Laravel
on startup. The modular pipelines use LiveKit Inference for Deepgram STT,
AssemblyAI STT, OpenAI LLM, and ElevenLabs TTS, so no separate provider keys are
needed for those services in v1.

System Settings also stores the agent runtime. When runtime is `Local FS PBX
Worker`, the dashboard controls the Supervisor service on this server. For
external, LiveKit Cloud hosted, or Telnyx hosted workers, deploy the worker in
that environment and set `FSPBX_BASE_URL` plus `FSPBX_AGENT_TOKEN` there so it
can call back to Laravel for PBX tools and transfers.

The OpenAI Realtime Speech-to-Speech engine uses the OpenAI Realtime plugin.
Only that engine needs the existing system OpenAI key in the main FS PBX `.env`:

```dotenv
OPENAI_API_KEY=
```

## Install

```bash
cd /var/www/fspbx/resources/ai-receptionist-agent
sudo mkdir -p /opt/fspbx/ai-receptionist-agent
sudo python3 -m venv /opt/fspbx/ai-receptionist-agent/.venv
sudo /opt/fspbx/ai-receptionist-agent/.venv/bin/pip install -r requirements.txt
/opt/fspbx/ai-receptionist-agent/.venv/bin/python -B -m agent.worker start
```

The source lives in the repository under `resources/ai-receptionist-agent`.
The Python virtual environment is installed under `/opt/fspbx` so dependency
files do not become untracked repository files.

The worker also starts a small local health endpoint. Check it with:

```bash
curl http://127.0.0.1:8097/
```

The supervisor template is stored at `install/ai-receptionist-agent.conf`.
After it is installed, Supervisor starts the local worker automatically on boot.
If AI Receptionists are disabled in FS PBX settings, the worker exits cleanly
instead of entering a restart loop.
