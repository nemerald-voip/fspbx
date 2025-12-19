-- flow_notify.lua
-- Immediate NOTIFY for Call Flow BLF (flow*XX)

local feature = argv[1]      -- "*26"
local domain  = argv[2]
local toggle  = argv[3] or "false"

-- DB logic: false = night = LED ON
local enabled = (toggle == "false")

local user = "flow" .. feature   -- flow*26
local userid = user .. "@" .. domain

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

    if enabled then
        ev:addHeader("answer-state", "confirmed")    -- LED ON
    else
        ev:addHeader("answer-state", "terminated")   -- LED OFF
    end

    freeswitch.consoleLog("NOTICE", "[flow_notify] Sending "..event_type.." for "..userid.." enabled="..tostring(enabled).."\n")

    ev:fire()
end

-- MUST send both — Polycom requires it
send_presence("PRESENCE_OUT")
send_presence("PRESENCE_IN")
