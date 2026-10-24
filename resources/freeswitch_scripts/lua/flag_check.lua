-- flag_check.lua
-- API script used inline by ring group and virtual receptionist dialplans:
--   <action application="set" data="flag_match=${lua(lua/flag_check.lua <domain_uuid> <flag> [<flag> ...])}" inline="true"/>
-- Each <flag> is a flag UUID, matched while the flag is active, or
-- <flag_uuid>:inactive, matched while it is inactive.
-- Writes the position (1, 2, ...) of the first flag in its wanted state, or 0
-- when none match. Any failure writes 0 so the call keeps its normal routing.

require "resources.functions.config"

local Database = require "resources.functions.database"

DEBUG_MODE = false -- Set to true to enable debug logs

local SCRIPT_NAME = "[flag_check.lua]"

local function debug_log(level, message)
    if DEBUG_MODE then
        freeswitch.consoleLog(level, SCRIPT_NAME .. " " .. tostring(message) .. "\n")
    end
end

local function check()
    local domain_uuid = tostring(argv[1] or "")

    if domain_uuid == "" or not argv[2] then
        debug_log("WARNING", "Missing domain or flag UUIDs; continuing with normal routing")
        return 0
    end

    local flag_state = require "lua.flag_state"

    local dbh = Database.new("system")
    if not (dbh and dbh:connected()) then
        debug_log("ERR", "Database unavailable; continuing with normal routing")
        return 0
    end

    local match = 0
    local position = 1
    while argv[position + 1] do
        local flag_uuid, wanted = tostring(argv[position + 1]):match("^([^:]+):?(%a*)$")
        local want_active = wanted ~= "inactive"
        local flag = flag_uuid and flag_state.load_flag_by_uuid(dbh, flag_uuid, domain_uuid)

        -- A missing or disabled flag never matches, so its row is skipped in either state.
        if flag and flag_state.truthy(flag.enabled) and flag_state.is_active(dbh, flag) == want_active then
            match = position
            break
        end

        debug_log("NOTICE", string.format("Flag %d (%s) is not %s", position, tostring(flag_uuid), want_active and "active" or "inactive"))
        position = position + 1
    end

    dbh:release()

    debug_log("NOTICE", "First matching flag position: " .. tostring(match))
    return match
end

local ok, match = pcall(check)
if not ok then
    freeswitch.consoleLog("ERR", SCRIPT_NAME .. " " .. tostring(match) .. "\n")
    match = 0
end

if stream then
    stream:write(tostring(match))
end
