-- Track agents' non-queue calls using FreeSWITCH's native channel counter.
-- Dialplan/originate: lua agent_call_track.lua caller|recipient
-- Directory generator: require "agent_call_track" (lookup only).
-- Directory-time membership lookup is cached; call-time tracking never queries FS PBX.
local DEBUG_MODE = false
local SCRIPT_NAME = "[agent_call_track.lua]"
local function log(level, message)
    freeswitch.consoleLog(level, SCRIPT_NAME .. " " .. tostring(message) .. "\n")
end
local function debug_log(message)
    if DEBUG_MODE then log("NOTICE", message) end
end
local function warning(message)
    log("WARNING", message)
end
local M = {}

function M.resolve(dbh, domain_uuid, domain_name, extension, warn)
    warn = warn or warning
    local matches = {}
    local ok, result, err = pcall(function()
        return dbh:query([[
            SELECT call_center_agent_uuid, agent_contact, agent_type
            FROM v_call_center_agents
            WHERE domain_uuid = :domain_uuid
              AND (agent_contact = :contact OR agent_id = :extension)
        ]], {
            domain_uuid = domain_uuid,
            contact = "user/" .. extension .. "@" .. domain_name,
            extension = extension,
        }, function(row)
            if row.agent_contact == "user/" .. extension .. "@" .. domain_name then
                matches[#matches + 1] = row
            else
                warn("Unsupported contact for agent " .. tostring(row.call_center_agent_uuid))
            end
        end)
    end)
    if not ok or not result or err then
        warn("Agent membership lookup failed; directory response will not be cached")
        return nil, false
    end
    if #matches > 1 then
        warn("Multiple agents use extension " .. extension .. "@" .. domain_name .. "; tracking omitted")
        return nil, true
    end
    if #matches == 1 then
        if matches[1].agent_type == "callback" then
            debug_log("Directory mapped agent " .. tostring(matches[1].call_center_agent_uuid))
            return matches[1].call_center_agent_uuid, true
        end
        warn("Unsupported agent type for " .. tostring(matches[1].call_center_agent_uuid))
    end
    return nil, true
end

function M.track(session, api, mode, warn)
    warn = warn or warning
    if not session then return end
    -- execute_on_originate runs in CS_ROUTING, before ready() becomes true.
    -- Tracking requires a live channel, not answered media or CS_EXECUTE.
    local state = session:getState()
    if state == "CS_HANGUP" or state == "CS_REPORTING" or state == "CS_DESTROY" or state == "CS_NONE" then return end
    local function get(name) return session:getVariable(name) or "" end
    if DEBUG_MODE then
        debug_log("Tracking check: channel=" .. get("uuid") .. ", mode=" .. tostring(mode) .. ", state=" .. tostring(state))
    end
    if get("cc_tracked_agent") ~= "" then debug_log("Skipped: channel already tracked"); return end
    if get("cc_side") == "agent" then debug_log("Skipped: native queue agent leg"); return end
    local agent = get("fspbx_cc_agent_uuid")
    if agent == "" then debug_log("Skipped: no directory agent identity"); return end
    local function uuid(value)
        return #value == 36 and value:match("^%x%x%x%x%x%x%x%x%-%x%x%x%x%-%x%x%x%x%-%x%x%x%x%-%x%x%x%x%x%x%x%x%x%x%x%x$")
    end
    local extension = get("fspbx_cc_extension")
    local alias = get("fspbx_cc_number_alias")
    local domain = get("fspbx_cc_domain_name")
    local function owns(user) return user ~= "" and (user == extension or (alias ~= "" and user == alias)) end
    if not uuid(agent) or not uuid(get("fspbx_cc_domain_uuid")) or not uuid(get("fspbx_cc_extension_uuid"))
        or extension == "" or domain == "" then
        warn("Invalid directory tracking identity")
        return
    end
    if mode == "caller" then
        if get("sip_authorized") ~= "true" or not owns(get("sip_auth_username"))
            or get("domain_uuid") ~= get("fspbx_cc_domain_uuid") or get("domain_name") ~= domain then
            debug_log("Skipped: authenticated caller does not own directory identity")
            return
        end
    elseif mode == "recipient" then
        if not owns(get("dialed_user")) or get("dialed_domain") ~= domain then
            debug_log("Skipped: recipient does not own directory identity")
            return
        end
    else
        return
    end
    local ok, err = pcall(function()
        if not tostring(api:executeString("module_exists mod_callcenter")):match("^true%s*$") then
            warn("mod_callcenter is unavailable; call continues without tracking")
            return
        end
        session:execute("callcenter_track", agent)
        if get("cc_tracked_agent") ~= agent then
            warn("FreeSWITCH did not track agent " .. agent .. "; call continues")
        elseif DEBUG_MODE then
            debug_log("Tracking active: channel=" .. get("uuid") .. ", agent=" .. agent)
        end
    end)
    if not ok then warn("Tracking failed; call continues: " .. tostring(err)) end
end

-- require must remain side-effect free, even if called in a Lua session.
if ... ~= "agent_call_track" then
    M.track(session, freeswitch.API(), argv and argv[1])
end

return M
