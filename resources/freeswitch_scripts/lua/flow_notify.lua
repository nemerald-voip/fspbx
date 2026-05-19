-- flow_notify.lua
-- Immediate NOTIFY for Call Flow BLF (flow<ext>)

local extension = tostring(argv[1] or "")   -- "333"
local domain    = tostring(argv[2] or "")
local toggle    = tostring(argv[3] or "false")

-- DB logic: false = alternate/night = LED ON
local enabled = (toggle == "false")

local user = "flow" .. extension            -- flow333
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
        ev:addHeader("answer-state", "confirmed")
    else
        ev:addHeader("answer-state", "terminated")
    end

    freeswitch.consoleLog("NOTICE", "[flow_notify] Sending " .. event_type .. " for " .. userid .. " enabled=" .. tostring(enabled) .. "\n")

    ev:fire()
end

send_presence("PRESENCE_OUT")
send_presence("PRESENCE_IN")