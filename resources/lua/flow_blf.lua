-- flow_blf.lua
-- Simple Call-Flow BLF presence daemon for FreeSWITCH
-- Listens for PRESENCE_PROBE and answers with current Call-Flow state from DB.
-- BLF AoR format: flow*<feature_code>@domain  (e.g. flow*26@fspbx.domain.com)

local CF_PREFIX = "flow"  -- user part starts with "flow"

local Database = require "resources.functions.database"

local function log(level, msg)
    freeswitch.consoleLog(level, "[flow_blf] " .. msg .. "\n")
end

-- --------------------------
-- DB: read call_flow_status
-- --------------------------
-- Return true  -> LED ON  (night mode)  when call_flow_status == 'false'
-- Return false -> LED OFF (day mode)    when call_flow_status == 'true'
local function get_callflow_enabled(feature_code, domain_name)
    local dbh = Database.new('system')
    if not (dbh and dbh:connected()) then
        log("ERR", "DB connect failed (system)")
        return nil
    end

    local sql = [[
        select cf.call_flow_status
        from v_call_flows cf
        join v_domains     d on cf.domain_uuid = d.domain_uuid
        where d.domain_name = :domain_name
          and cf.call_flow_feature_code = :feature_code
    ]]

    local status, err = dbh:first_value(sql, {
        domain_name  = domain_name,
        feature_code = feature_code,
    })

    dbh:release()

    if err then
        log("ERR", "DB error: " .. tostring(err))
        return nil
    end
    if not status or status == "" then
        return nil
    end

    -- call_flow_status = 'false' means NIGHT mode (lamp ON)
    return (status == "false")
end

-- --------------------------
-- Publish presence for AoR
-- --------------------------
local function publish_flow_presence(user, domain, enabled)
    local ev = freeswitch.Event("PRESENCE_IN")

    ev:addHeader("proto", "sip")

    ev:addHeader("status", "Active (1 waiting)")
    ev:addHeader("rpid", "unknown")
    ev:addHeader("event_count", "1")

    ev:addHeader("event_type", "presence")
    ev:addHeader("alt_event_type", "dialog")

    ev:addHeader("from",  user .. '@' .. domain)
    ev:addHeader("login", user .. '@' .. domain)

    local uuid = freeswitch.API():execute("create_uuid")
    ev:addHeader("unique-id", uuid)
    ev:addHeader("Presence-Call-Direction", "outbound")

    if enabled then
        ev:addHeader("answer-state", "confirmed")   -- LED ON
    else
        ev:addHeader("answer-state", "terminated")  -- LED OFF
    end

    log("NOTICE", string.format("Publish BLF: user=%s enabled=%s", user .. '@' .. domain, tostring(enabled)))
    ev:fire()
end

-- --------------------------
-- Normalize To: header → user@domain
-- Handles: <sip:flow*26@domain;param=...>, sip:..., angle brackets, params
-- --------------------------
local function normalize_to_uri(to)
    if not to or to == "" then return "", "" end
    -- trim angle brackets
    to = to:gsub("^%s*<", ""):gsub(">%s*$", "")
    -- strip leading sip:
    to = to:gsub("^sip:", "")
    -- drop any ;params
    to = (to:match("([^;]+)")) or to
    -- now split user@domain
    local user, domain = to:match("^(.-)@(.-)$")
    return user or "", domain or ""
end

-- --------------------------
-- Handle PRESENCE_PROBE
-- --------------------------
local function handle_probe(event)
    local to_hdr = event:getHeader("to") or ""
    local expires = tonumber(event:getHeader("expires") or "0")

    if not expires or expires <= 0 then
        return
    end

    local user, domain = normalize_to_uri(to_hdr)
    if user == "" or domain == "" then
        log("DEBUG", "Ignoring PRESENCE_PROBE with unexpected To=: " .. to_hdr)
        return
    end

    if user:sub(1, 5) == "flow+" then
        return
    end

    -- only handle "flow..." AoRs; explicitly ignore "flow+..."
    if user:sub(1, #CF_PREFIX) ~= CF_PREFIX then
        return
    end

    -- extract EXACT feature part after "flow"
    local feature_code = user:sub(#CF_PREFIX + 1)

    log("NOTICE", string.format("PRESENCE_PROBE Call-Flow: to=%s user=%s feature=%s domain=%s",
        to_hdr, user, feature_code, domain))

    local enabled = get_callflow_enabled(feature_code, domain)
    if enabled == nil then
        log("WARNING", "No Call-Flow record for feature=" .. feature_code .. " domain=" .. domain)
        enabled = false
    end

    publish_flow_presence(user, domain, enabled)
end

-- --------------------------
-- Main loop
-- --------------------------
local function main()
    log("NOTICE", "flow_blf.lua starting (prefix=" .. CF_PREFIX .. ")")

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
