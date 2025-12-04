-- followme_blf.lua
-- Simple FollowMe BLF presence daemon for FreeSWITCH
-- Listens for PRESENCE_PROBE and answers with current FollowMe state from DB. 

local FM_PREFIX = "fm"  -- BLF AoR will be like "fm100@10001.fspbx.com"

local Database = require "resources.functions.database"

local function log(level, msg)
    freeswitch.consoleLog(level, "[followme_blf] " .. msg .. "\n")
end

-- --------------------------
-- DB: read follow_me_enabled
-- --------------------------
local function get_followme_enabled(ext, domain_name)
    local dbh = Database.new('system')
    if not (dbh and dbh:connected()) then
        log("ERR", "DB connect failed (system)")
        return nil
    end

    local sql = [[
        select e.follow_me_enabled
        from v_extensions e
        join v_domains   d on e.domain_uuid = d.domain_uuid
        where d.domain_name = :domain_name and e.extension = :extension 
    ]]

    local enabled, err = dbh:first_value(sql, {
        domain_name = domain_name,
        extension   = ext,
    })

    -- Close DB connection
    dbh:release()

    if err then
        log("ERR", "DB error: " .. tostring(err))
        return nil
    end

    if not enabled then
        return nil
    end


    enabled = string.lower(enabled)
    return (enabled == "true" or enabled == "1")
end

-- --------------------------
-- Publish presence for AoR
-- --------------------------
local function publish_followme_presence(user, domain, enabled)
    local ev = freeswitch.Event("PRESENCE_IN")

    ev:addHeader("proto", "sip")

    ev:addHeader("status", "Active (1 waiting)");
	ev:addHeader("rpid", "unknown");
	ev:addHeader("event_count", "1");

    ev:addHeader("event_type", "presence")
    ev:addHeader("alt_event_type", "dialog")

    ev:addHeader("from", user .. '@' .. domain)
    ev:addHeader("login", user .. '@' .. domain)

    local uuid = freeswitch.API():execute("create_uuid")
    ev:addHeader("unique-id", uuid)
    ev:addHeader("Presence-Call-Direction", "outbound")

    if enabled then
        ev:addHeader("answer-state", "confirmed")
    else
        ev:addHeader("answer-state", "terminated")
    end

    log("NOTICE", string.format("Publish BLF: user=%s enabled=%s", user .. '@' .. domain, tostring(enabled)))
    ev:fire()
end

-- --------------------------
-- Handle PRESENCE_PROBE
-- --------------------------
local function handle_probe(event)
    local to = event:getHeader("to") or ""
    local expires = tonumber(event:getHeader("expires") or "0")

    if not expires or expires <= 0 then
        return
    end

    local user, domain = to:match("^(.-)@(.-)$")
    if not user or not domain then
        log("DEBUG", "Ignoring PRESENCE_PROBE with unexpected to=" .. to)
        return
    end

    -- we only handle fm+<ext>@domain
    if user:sub(1, #FM_PREFIX) ~= FM_PREFIX then
        return
    end

    local ext = user:sub(#FM_PREFIX + 1)

    log("NOTICE", string.format("PRESENCE_PROBE FollowMe: to=%s ext=%s domain=%s", to, ext, domain))

    local enabled = get_followme_enabled(ext, domain)
    if enabled == nil then
        log("WARNING", "No FollowMe record for ext=" .. ext .. " domain=" .. domain)
        enabled = false
    end

    publish_followme_presence(user, domain, enabled)
end

-- --------------------------
-- Main loop
-- --------------------------
local function main()
    log("NOTICE", "followme_blf.lua starting (prefix=" .. FM_PREFIX .. ")")

    local consumer = freeswitch.EventConsumer("PRESENCE_PROBE")

    while true do
        local ev = consumer:pop(10)  -- 10s timeout
        if ev then
            local name = ev:getHeader("Event-Name") or ""
            if name == "PRESENCE_PROBE" then
                pcall(handle_probe, ev)
            end
        end
    end
end

main()
