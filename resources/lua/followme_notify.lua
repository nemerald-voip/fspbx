-- followme_notify.lua
-- Usage from ESL:
--   luarun lua/followme_notify.lua 100 10001.fspbx.com true

local ext    = argv[1]
local domain = argv[2]
local enabled_arg = argv[3] or "false"

local enabled = (enabled_arg == "true" or enabled_arg == "1" or enabled_arg == "yes")

local FM_PREFIX = "fm"

local function log(level, msg)
    freeswitch.consoleLog(level, "[followme_notify] " .. msg .. "\n")
end

local function publish_followme_presence(user, domain, enabled)
    local ev = freeswitch.Event("PRESENCE_IN")

    ev:addHeader("proto", "sip")

    ev:addHeader("status", "Active (1 waiting)")
    ev:addHeader("rpid", "unknown")
    ev:addHeader("event_count", "1")

    ev:addHeader("event_type", "presence")
    ev:addHeader("alt_event_type", "dialog")

    ev:addHeader("from",  user .. "@" .. domain)
    ev:addHeader("login", user .. "@" .. domain)

    local uuid = freeswitch.API():execute("create_uuid")
    ev:addHeader("unique-id", uuid)
    ev:addHeader("Presence-Call-Direction", "outbound")

    if enabled then
        ev:addHeader("answer-state", "confirmed")
    else
        ev:addHeader("answer-state", "terminated")
    end

    log("NOTICE", string.format(
        "Fire presence for FollowMe user=%s@%s enabled=%s",
        user, domain, tostring(enabled)
    ))

    ev:fire()
end

if not ext or not domain then
    log("ERR", "Missing args. Usage: luarun lua/followme_notify.lua <ext> <domain> <true|false>")
    return
end

-- user string should match BLF AoR user part: "fm100"
local user = FM_PREFIX .. ext

publish_followme_presence(user, domain, enabled)
