-- flag_notify.lua
-- Immediate BLF NOTIFY for a Flag key (flag<number>@domain).
-- Usage: luarun lua/flag_notify.lua <flag_number> <domain_name> <true|false>
--   true  = flag active   = LED on
--   false = flag inactive = LED off

local flag_number = tostring(argv[1] or "")
local domain      = tostring(argv[2] or "")
local active      = tostring(argv[3] or "false") == "true"

if flag_number == "" or domain == "" then
    freeswitch.consoleLog("WARNING", "[flag_notify] Missing flag number or domain\n")
    return
end

local userid = "flag" .. flag_number .. "@" .. domain

local api = freeswitch.API()
local uuid = api:execute("create_uuid")

local function send_presence(event_type)
    local ev = freeswitch.Event(event_type)

    ev:addHeader("proto", "sip")
    ev:addHeader("event_type", "presence")
    ev:addHeader("alt_event_type", "dialog")
    ev:addHeader("Presence-Call-Direction", "outbound")
    ev:addHeader("from", userid)
    ev:addHeader("login", userid)
    ev:addHeader("unique-id", uuid)
    ev:addHeader("status", "Active (1 waiting)")
    ev:addHeader("event_count", "1")
    ev:addHeader("rpid", "unknown")
    ev:addHeader("answer-state", active and "confirmed" or "terminated")

    ev:fire()
end

send_presence("PRESENCE_OUT")
send_presence("PRESENCE_IN")
