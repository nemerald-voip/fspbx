-- csta_app.lua
--
-- Uses the experimental native sofia_csta_call API. All SIP transport,
-- registration lookup, NAT routing and dialog handling remain inside Sofia.
--
-- Usage:
--   luarun csta_app.lua <extension> <destination> [domain] [profile] <local-ip> [sdp] [timeout-seconds]
--
-- Example:
--   luarun csta_app.lua 203 18004444444 shapeint.pbx02.jcnt.net internal 10.10.172.119
--   luarun csta_app.lua 203 *98 shapeint.pbx02.jcnt.net internal 10.10.172.119 sdp 20

local extension = argv[1] or "203"
local destination = argv[2] or "18004444444"
local domain = argv[3] or "shapeint.pbx02.jcnt.net"
local profile = argv[4] or "internal"
local local_ip = argv[5]
local mode = nil
local timeout_seconds = 20

if argv[6] then
    if tonumber(argv[6]) then
        timeout_seconds = tonumber(argv[6])
    else
        mode = argv[6]
        timeout_seconds = tonumber(argv[7]) or timeout_seconds
    end
end

local function log(level, message)
    freeswitch.consoleLog(level, "uaCSTA: " .. message .. "\n")
end

local function trim(value)
    return (value or ""):gsub("^%s+", ""):gsub("%s+$", "")
end

if not extension:match("^[%w_.+%-]+$") then
    log("ERR", "invalid extension: " .. extension)
    return
end

if not destination:match("^[%w_.%-%+%*#]+$") then
    log("ERR", "invalid destination: " .. destination)
    return
end

if not domain:match("^[%w_.%-]+$") then
    log("ERR", "invalid domain: " .. domain)
    return
end

if not profile:match("^[%w_.%-]+$") then
    log("ERR", "invalid Sofia profile: " .. profile)
    return
end

if mode and mode ~= "sdp" then
    log("ERR", "invalid mode: " .. mode .. " (expected sdp)")
    return
end

local api = freeswitch.API()
local events = freeswitch.EventConsumer("CUSTOM", "sofia::csta")
local command = string.format(
    "%s %s@%s %s",
    profile,
    extension,
    domain,
    destination
)

if not local_ip or not local_ip:match("^%d+%.%d+%.%d+%.%d+$") then
    log("ERR", "a valid local IP address is required")
    return
end
command = command .. " " .. local_ip
if mode then
    command = command .. " " .. mode
end
local result = trim(api:execute("sofia_csta_call", command))

if not result:match("^%+OK%s+") then
    log("ERR", "unable to start CSTA transaction: " .. result)
    return
end

local dialog_ids = {}
local dialog_id_text = trim(result:gsub("^%+OK%s+", ""))

for dialog_id in dialog_id_text:gmatch("[%w%-]+") do
    dialog_ids[dialog_id] = true
end

log("NOTICE", "started CSTA dialog(s): " .. dialog_id_text)

local deadline = os.time() + timeout_seconds

while os.time() < deadline do
    local event = events:pop(1, 1000)
	local event_dialog_id = event and event:getHeader("CSTA-Dialog-ID") or nil

    if event_dialog_id and dialog_ids[event_dialog_id] then
        local action = event:getHeader("CSTA-Action") or "unknown"
        local status = event:getHeader("CSTA-SIP-Status") or ""
        local phrase = event:getHeader("CSTA-SIP-Phrase") or ""
        local body = event:getBody() or ""

        log("NOTICE", string.format("%s: %s %s", action, status, phrase))

        if body ~= "" then
            log("NOTICE", "response body: " .. body)
        end

        if action == "bye-response" or action == "incoming-bye" or action == "terminated" then
            dialog_ids[event_dialog_id] = nil
        end

        if action == "invite-response" and tonumber(status) and tonumber(status) >= 300 then
            dialog_ids[event_dialog_id] = nil
        end

        if next(dialog_ids) == nil then return end
    end
end

log("WARNING", "timed out waiting for CSTA transaction completion")
