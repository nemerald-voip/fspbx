-- flow_blf.lua
-- Simple Call-Flow BLF presence daemon for FreeSWITCH
-- Listens for PRESENCE_PROBE and answers with current Call-Flow state from DB.
-- BLF AoR format: flow<extension>@domain  (e.g. flow333@fspbx.domain.com)

require "resources.functions.config"

local Database = require "resources.functions.database"

local CF_PREFIX   = "flow"
local DEBUG_MODE  = true
local SCRIPT_NAME = "[flow_blf.lua]"

local api = freeswitch.API()

-- Debug logging function
local function debug_log(level, message)
    if DEBUG_MODE then
        freeswitch.consoleLog(level, SCRIPT_NAME .. " " .. tostring(message) .. "\n")
    end
end

-- --------------------------
-- Normalize To: header → user@domain
-- Handles:
--   <sip:flow333@domain;param=...>
--   sip:flow333@domain;param=...
--   flow333@domain
-- --------------------------
local function normalize_to_uri(to)
    if not to or to == "" then
        return "", ""
    end

    local uri = to:match("<%s*sips?:([^>]+)>")

    if not uri then
        uri = to:match("sips?:([^;>]+)")
    end

    if not uri then
        uri = to
    end

    uri = tostring(uri)
    uri = uri:gsub("^%s+", ""):gsub("%s+$", "")
    uri = uri:gsub("^sips?:", "")
    uri = (uri:match("([^;]+)")) or uri

    local user, domain = uri:match("^([^@]+)@(.+)$")
    return user or "", domain or ""
end

-- --------------------------
-- DB: read call_flow_status
-- Return true  -> LED ON  (night/alternate mode) when call_flow_status == 'false'
-- Return false -> LED OFF (day/normal mode)      when call_flow_status == 'true'
-- --------------------------
local function get_callflow_enabled(extension, domain_name)
    local dbh = Database.new("system")
    if not (dbh and dbh:connected()) then
        debug_log("ERR", "DB connect failed (system)")
        return nil
    end

    local sql = [[
        SELECT cf.call_flow_status
        FROM v_call_flows cf
        JOIN v_domains d ON cf.domain_uuid = d.domain_uuid
        WHERE d.domain_name = :domain_name
          AND cf.call_flow_extension = :extension
        LIMIT 1
    ]]

    local status, err = dbh:first_value(sql, {
        domain_name = domain_name,
        extension   = extension,
    })

    dbh:release()

    if err then
        debug_log("ERR", "DB error: " .. tostring(err))
        return nil
    end

    if status == nil or status == "" then
        return nil
    end

    local normalized = tostring(status):lower()

    -- false/f/0 means alternate mode is active -> LED ON
    return (normalized == "false" or normalized == "f" or normalized == "0")
end

-- --------------------------
-- Publish presence for AoR
-- --------------------------
local function publish_one_presence(event_type, user, domain, enabled)
    local ev = freeswitch.Event(event_type)

    ev:addHeader("proto", "sip")
    ev:addHeader("event_type", "presence")
    ev:addHeader("alt_event_type", "dialog")
    ev:addHeader("Presence-Call-Direction", "outbound")
    ev:addHeader("from", user .. "@" .. domain)
    ev:addHeader("login", user .. "@" .. domain)
    ev:addHeader("unique-id", api:execute("create_uuid"))
    ev:addHeader("status", "Active (1 waiting)")
    ev:addHeader("event_count", "1")
    ev:addHeader("rpid", "unknown")

    if enabled then
        ev:addHeader("answer-state", "confirmed")   -- LED ON
    else
        ev:addHeader("answer-state", "terminated")  -- LED OFF
    end

    debug_log("NOTICE", string.format(
        "Publishing %s for %s enabled=%s",
        event_type,
        user .. "@" .. domain,
        tostring(enabled)
    ))

    ev:fire()
end

local function publish_flow_presence(user, domain, enabled)
    -- Some phones care about both
    publish_one_presence("PRESENCE_OUT", user, domain, enabled)
    publish_one_presence("PRESENCE_IN",  user, domain, enabled)
end

-- --------------------------
-- Handle PRESENCE_PROBE
-- --------------------------
local function handle_probe(event)
    local to_hdr  = event:getHeader("to") or ""
    local expires = tonumber(event:getHeader("expires") or "0")

    if not expires or expires <= 0 then
        debug_log("DEBUG", "Ignoring probe with expires <= 0")
        return
    end

    local user, domain = normalize_to_uri(to_hdr)
    if user == "" or domain == "" then
        debug_log("DEBUG", "Ignoring PRESENCE_PROBE with unexpected To=: " .. tostring(to_hdr))
        return
    end

    -- Ignore odd formats like flow+...
    if user:sub(1, 5) == "flow+" then
        debug_log("DEBUG", "Ignoring flow+ style probe for user=" .. user)
        return
    end

    -- Only handle flow<extension>@domain
    if user:sub(1, #CF_PREFIX) ~= CF_PREFIX then
        return
    end

    local extension = user:sub(#CF_PREFIX + 1)
    if extension == "" then
        debug_log("DEBUG", "Ignoring flow probe with empty extension: " .. user)
        return
    end

    debug_log("NOTICE", string.format(
        "PRESENCE_PROBE Call-Flow: to=%s user=%s extension=%s domain=%s",
        tostring(to_hdr),
        tostring(user),
        tostring(extension),
        tostring(domain)
    ))

    local enabled = get_callflow_enabled(extension, domain)
    if enabled == nil then
        debug_log("WARNING", "No Call-Flow record for extension=" .. tostring(extension) .. " domain=" .. tostring(domain))
        enabled = false
    end

    publish_flow_presence(user, domain, enabled)
end

-- --------------------------
-- Main loop
-- --------------------------
local function main()
    debug_log("NOTICE", "Starting flow BLF daemon (prefix=" .. CF_PREFIX .. ")")

    local consumer = freeswitch.EventConsumer("PRESENCE_PROBE")

    while true do
        local ev = consumer:pop(10) -- 10 second timeout
        if ev then
            local name = ev:getHeader("Event-Name") or ""
            if name == "PRESENCE_PROBE" then
                local ok, err = pcall(handle_probe, ev)
                if not ok then
                    debug_log("ERR", "handle_probe failed: " .. tostring(err))
                end
            end
        end
    end
end

main()