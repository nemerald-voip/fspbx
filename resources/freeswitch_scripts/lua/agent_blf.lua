-- agent_blf.lua
-- Call-center agent BLF presence daemon for FreeSWITCH
--
-- Behavior:
--   Phone SUBSCRIBEs to:  agent100@domain or break100@domain
--   -> FreeSWITCH generates PRESENCE_PROBE
--   -> This script resolves agent 100 on that domain
--   -> The live mod_callcenter status controls both BLF lamps
--
-- Agent BLF:
--   Available / Available (On Demand) / On Break => LED ON
--   Logged Out                                    => LED OFF
--
-- Break BLF:
--   On Break  => LED ON
--   otherwise => LED OFF

local AGENT_PREFIX = "agent"
local BREAK_PREFIX = "break"

local Database = require "resources.functions.database"

local function log(level, msg)
    freeswitch.consoleLog(level, "[agent_blf] " .. msg .. "\n")
end

local api = freeswitch.API()

---------------------------------------------------------
-- Normalize To: header -> user@domain
-- Handles: <sip:agent100@domain;param=...>, sip:..., params
---------------------------------------------------------
local function normalize_to_uri(to)
    if not to or to == "" then return "", "" end

    -- extract the URI from angle brackets when present
    to = to:match("<%s*sips?:([^>]+)>") or to

    -- strip leading sip: or sips:
    to = to:gsub("^sips?:", "")

    -- drop any ;params
    to = (to:match("([^;]+)")) or to

    -- now split user@domain
    local user, domain = to:match("^(.-)@(.-)$")
    return user or "", domain or ""
end

---------------------------------------------------------
-- Parse agent<id> or break<id>
---------------------------------------------------------
local function parse_blf_user(user)
    local agent_id = user:match("^" .. AGENT_PREFIX .. "(%d+)$")
    if agent_id then return "agent", agent_id end

    agent_id = user:match("^" .. BREAK_PREFIX .. "(%d+)$")
    if agent_id then return "break", agent_id end

    return nil, nil
end

---------------------------------------------------------
-- Resolve agent_id@domain to the runtime agent UUID
---------------------------------------------------------
local function get_agent_by_id(agent_id, domain_name)
    local dbh = Database.new('system')
    if not (dbh and dbh:connected()) then
        log("ERR", "DB connect failed (system)")
        return nil
    end

    local row, err = dbh:first_row([[
        select a.call_center_agent_uuid, a.agent_id, d.domain_name
        from v_call_center_agents a
        join v_domains d on d.domain_uuid = a.domain_uuid
        where a.agent_id = :agent_id
          and d.domain_name = :domain_name
        limit 1
    ]], {
        agent_id    = agent_id,
        domain_name = domain_name,
    })

    dbh:release()

    if err then
        log("ERR", "DB error (agent lookup): " .. tostring(err))
        return nil
    end

    return row
end

---------------------------------------------------------
-- Resolve the runtime agent UUID from callcenter::info
---------------------------------------------------------
local function get_agent_by_uuid(agent_uuid)
    local dbh = Database.new('system')
    if not (dbh and dbh:connected()) then
        log("ERR", "DB connect failed (system)")
        return nil
    end

    local row, err = dbh:first_row([[
        select a.call_center_agent_uuid, a.agent_id, d.domain_name
        from v_call_center_agents a
        join v_domains d on d.domain_uuid = a.domain_uuid
        where a.call_center_agent_uuid = :agent_uuid
        limit 1
    ]], { agent_uuid = agent_uuid })

    dbh:release()

    if err then
        log("ERR", "DB error (runtime agent lookup): " .. tostring(err))
        return nil
    end

    return row
end

---------------------------------------------------------
-- Read the live mod_callcenter status
---------------------------------------------------------
local function get_agent_status(agent_uuid)
    local status = tostring(api:executeString(
        "callcenter_config agent get status " .. tostring(agent_uuid)
    ) or "")

    status = status:gsub("^%s+", ""):gsub("%s+$", "")

    if status == "Available"
        or status == "Available (On Demand)"
        or status == "On Break"
        or status == "Logged Out" then
        return status
    end

    log("WARNING", "Unknown live status for runtime agent " .. tostring(agent_uuid))
    return nil
end

---------------------------------------------------------
-- Convert a live status to the two lamp states
---------------------------------------------------------
local function get_presence_states(status)
    local logged_in = status == "Available"
        or status == "Available (On Demand)"
        or status == "On Break"

    local on_break = status == "On Break"

    return logged_in, on_break
end

---------------------------------------------------------
-- Publish presence for agent<id> or break<id>
---------------------------------------------------------
local function publish_agent_presence(user, domain, enabled, status)
    local ev = freeswitch.Event("PRESENCE_IN")

    ev:addHeader("proto", "sip")

    ev:addHeader("status", status or "Logged Out")
    ev:addHeader("rpid", "unknown")
    ev:addHeader("event_count", "1")

    ev:addHeader("event_type", "presence")
    ev:addHeader("alt_event_type", "dialog")

    ev:addHeader("from",  user .. '@' .. domain)
    ev:addHeader("login", user .. '@' .. domain)

    local uuid = api:execute("create_uuid")
    ev:addHeader("unique-id", uuid)
    ev:addHeader("Presence-Call-Direction", "outbound")

    if enabled then
        ev:addHeader("answer-state", "confirmed")   -- LED ON
    else
        ev:addHeader("answer-state", "terminated")  -- LED OFF
    end

    log("NOTICE", string.format(
        "Publish Agent BLF: user=%s@%s enabled=%s status=%s",
        user, domain, tostring(enabled), tostring(status)
    ))

    ev:fire()
end

---------------------------------------------------------
-- Publish both lamps after a call-center status change
---------------------------------------------------------
local function publish_agent_status(agent_id, domain_name, status)
    local logged_in, on_break = get_presence_states(status)

    publish_agent_presence(AGENT_PREFIX .. agent_id, domain_name, logged_in, status)
    publish_agent_presence(BREAK_PREFIX .. agent_id, domain_name, on_break, status)
end

---------------------------------------------------------
-- Handle callcenter::info agent status changes
---------------------------------------------------------
local function handle_callcenter_event(event)
    local action = event:getHeader("CC-Action") or ""
    if action ~= "agent-status-change" then return end

    local agent_uuid = event:getHeader("CC-Agent") or ""
    if agent_uuid == "" then return end

    local agent = get_agent_by_uuid(agent_uuid)
    if not agent then
        log("WARNING", "No agent record for runtime UUID=" .. tostring(agent_uuid))
        return
    end

    local status = event:getHeader("CC-Agent-Status") or ""
    if status == "" then
        status = get_agent_status(agent.call_center_agent_uuid)
    end

    if not status or status == "" then return end

    log("NOTICE", string.format(
        "AGENT_STATUS_CHANGE agent=%s domain=%s status=%s",
        tostring(agent.agent_id), tostring(agent.domain_name), tostring(status)
    ))

    publish_agent_status(
        tostring(agent.agent_id),
        tostring(agent.domain_name),
        status
    )
end

---------------------------------------------------------
-- Handle PRESENCE_PROBE for agent<id> or break<id>
---------------------------------------------------------
local function handle_probe(event)
    local to = event:getHeader("to") or ""
    local expires = tonumber(event:getHeader("expires") or "0")

    if not expires or expires <= 0 then return end

    local user, domain = normalize_to_uri(to)
    if user == "" or domain == "" then
        return
    end

    local key_type, agent_id = parse_blf_user(user)
    if not key_type then return end

    log("NOTICE", string.format(
        "PRESENCE_PROBE Agent: to=%s type=%s agent=%s domain=%s",
        to, key_type, agent_id, domain
    ))

    local agent = get_agent_by_id(agent_id, domain)
    if not agent then
        log("WARNING", string.format(
            "No agent record for agent_id=%s domain=%s",
            tostring(agent_id), tostring(domain)
        ))
        publish_agent_presence(user, domain, false, "Logged Out")
        return
    end

    local status = get_agent_status(agent.call_center_agent_uuid)
    if not status then return end

    local logged_in, on_break = get_presence_states(status)
    local enabled = key_type == "agent" and logged_in or on_break

    publish_agent_presence(user, domain, enabled, status)
end

---------------------------------------------------------
-- Main loop
---------------------------------------------------------
local function main()
    log("NOTICE", "agent_blf.lua starting")

    -- One consumer for SUBSCRIBE probes (phones asking "what's the state?")
    local consumer_probe = freeswitch.EventConsumer("PRESENCE_PROBE")
    -- One consumer for live mod_callcenter agent status changes
    local consumer_callcenter = freeswitch.EventConsumer("CUSTOM", "callcenter::info")

    while true do
        local did_something = false

        -- handle all probes waiting
        while true do
            local ev = consumer_probe:pop(0)
            if not ev then break end
            pcall(handle_probe, ev)
            did_something = true
        end

        -- handle all call-center events waiting
        while true do
            local cc_ev = consumer_callcenter:pop(0)
            if not cc_ev then break end
            pcall(handle_callcenter_event, cc_ev)
            did_something = true
        end

        if not did_something then
            freeswitch.msleep(50)
        end
    end
end

main()
