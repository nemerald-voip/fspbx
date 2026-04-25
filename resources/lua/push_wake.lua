-- push_wake.lua
--
-- Fires the APNs VoIP push for an incoming call to a push-enabled extension
-- and waits briefly for the device to re-REGISTER, so local_extension can
-- bridge to it when the iOS app is backgrounded/closed.
--
-- Invoked from the FreeSWITCH dialplan as an early `continue="true"` extension
-- (order < local_extension) so this script runs before the user lookup tries
-- to bridge. If the extension has no apns_voip_token the script returns a
-- no-op and normal dialplan flow is unchanged.
--
-- Flow:
--   1. Resolve extension_uuid + apns_voip_token via user_data API.
--   2. If no token, return — this is a regular SIP phone; nothing to do.
--   3. Flush any stale WebRTC registration so the woken app is forced to
--      re-register fresh (a force-quit iOS app leaves a dead WSS contact
--      that bridge would happily target if we didn't clear it first).
--   4. Fire-and-forget HTTP POST to the push webhook with event=incoming_call,
--      which the Laravel side dispatches to SendIncomingCallPushJob.
--   5. preAnswer the inbound leg so the caller hears early media (ringback)
--      while we wait for the phone to wake and re-register.
--   6. Poll sofia_contact every PUSH_WAKE_POLL_MS for up to PUSH_WAKE_TIMEOUT_MS;
--      return as soon as a contact appears (local_extension bridge will
--      succeed), or at timeout (falls through to forward_user_not_registered).
--
-- Configurable via FreeSWITCH global vars (set in vars.xml):
--   ${push_webhook_url}    full URL of the Laravel /webhook/freeswitch endpoint
--   ${push_webhook_secret} HMAC-SHA256 secret matching APP_FREESWITCH_WEBHOOK_SECRET
--
-- Defaults below match the conventional install (Laravel on the same host).

local SCRIPT_NAME = "[push_wake.lua]"
local PUSH_WAKE_TIMEOUT_MS = 15000
local PUSH_WAKE_POLL_MS = 500

local json = require "resources.functions.lunajson"
local api = freeswitch.API()

local WEBHOOK_URL = api:executeString("global_getvar push_webhook_url")
if not WEBHOOK_URL or WEBHOOK_URL == "" then
    WEBHOOK_URL = "http://127.0.0.1/webhook/freeswitch"
end

local WEBHOOK_SECRET = api:executeString("global_getvar push_webhook_secret")
if not WEBHOOK_SECRET or WEBHOOK_SECRET == "" then
    -- No secret configured — bail. Without HMAC the webhook handler will
    -- reject the request anyway.
    freeswitch.consoleLog("WARNING", SCRIPT_NAME .. " push_webhook_secret not set — skipping push\n")
    return
end

local function log(level, msg)
    freeswitch.consoleLog(level, SCRIPT_NAME .. " " .. tostring(msg) .. "\n")
end

local function api_value(cmd)
    local v = api:executeString(cmd)
    if not v then return "" end
    v = v:gsub("%s+$", "")
    -- Treat sofia's user-not-registered response (and related error/* strings)
    -- as "no value" — otherwise api_value returns the error text verbatim and
    -- the sofia_contact poll loop mistakes it for a valid contact URI,
    -- exiting before the push-woken app has re-registered.
    if v == "" or v:match("^%-ERR") or v:match("^error/") or v == "_undef_" then return "" end
    return v
end

local function shell_quote(s)
    return "'" .. tostring(s or ""):gsub("'", "'\\''") .. "'"
end

if not session or not session:ready() then
    return
end

local destination_number = session:getVariable("destination_number") or ""
local domain_name = session:getVariable("domain_name") or ""
local caller_id_name = session:getVariable("caller_id_name") or "Unknown"
local caller_id_number = session:getVariable("caller_id_number") or ""
local call_uuid = session:getVariable("uuid") or ""

if destination_number == "" or domain_name == "" then
    return
end

local aor = destination_number .. "@" .. domain_name

local extension_uuid = api_value("user_data " .. aor .. " var extension_uuid")
local apns_token = api_value("user_data " .. aor .. " var apns_voip_token")

if apns_token == "" then
    -- No push token registered — regular SIP phone, nothing to do.
    return
end

-- Flush any existing WebRTC registration for this extension before waking
-- the app. When iOS force-quits, the WSS dies but the sofia registration
-- persists until expiry — bridge would then target the dead contact, the
-- caller hears endless ringback and the pushed app gets no SIP INVITE.
-- Clearing first forces the woken app to register fresh.
api:executeString("sofia profile webrtc flush_inbound_reg " .. aor .. " reboot")

local payload = json.encode({
    event = "incoming_call",
    timestamp = os.date("!%Y-%m-%dT%H:%M:%SZ"),
    data = {
        extension_uuid = extension_uuid,
        extension_number = destination_number,
        domain_name = domain_name,
        caller_id_name = caller_id_name,
        caller_id_number = caller_id_number,
        call_uuid = call_uuid,
        did_e164 = destination_number,
    },
})

local hmac_cmd = string.format(
    "printf %%s %s | openssl dgst -sha256 -hmac %s | awk '{print $NF}'",
    shell_quote(payload), shell_quote(WEBHOOK_SECRET)
)
local hmac_handle = io.popen(hmac_cmd)
local signature = hmac_handle and hmac_handle:read("*a") or ""
if hmac_handle then hmac_handle:close() end
signature = signature:gsub("%s+", "")

local curl_cmd = string.format(
    "(curl -k -s -m 5 -X POST -H 'Content-Type: application/json' -H 'Signature: %s' -d %s %s >/dev/null 2>&1) &",
    signature, shell_quote(payload), shell_quote(WEBHOOK_URL)
)
os.execute(curl_cmd)
log("INFO", "dispatched incoming_call webhook for " .. aor)

-- Force the outbound B-leg (iOS endpoint) to use the A-leg channel UUID as
-- its SIP Call-ID, matching the push payload's `call_uuid`. Without this,
-- mod_sofia mints a fresh Call-ID and CallKit's answer UUID (from push)
-- doesn't align with CallManager's callUUID (from INVITE), so the answer
-- guard fails and the call is BYE'd.
if call_uuid ~= "" then
    session:execute("export", "nolocal:sip_invite_call_id=" .. call_uuid)
end

if not session:ready() then return end
session:preAnswer()

-- Poll for any contact reappearing — local_extension downstream will bridge
-- to whichever contacts mod_sofia has at that point.
local deadline_attempts = math.floor(PUSH_WAKE_TIMEOUT_MS / PUSH_WAKE_POLL_MS)
local attempt = 0
while attempt < deadline_attempts do
    if not session:ready() then return end
    local contact = api_value("sofia_contact " .. aor)
    if contact ~= "" then
        log("INFO", string.format("registered after %dms", attempt * PUSH_WAKE_POLL_MS))
        return
    end
    session:sleep(PUSH_WAKE_POLL_MS)
    attempt = attempt + 1
end

log("NOTICE", string.format("timeout waiting for re-register on %s", aor))
