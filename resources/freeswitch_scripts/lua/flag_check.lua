-- flag_check.lua
-- API script used inline by ring group and virtual receptionist dialplans:
--   <action application="set" data="flag_match=${lua(lua/flag_check.lua <domain_uuid> <flag_uuid> [<flag_uuid> ...])}" inline="true"/>
-- Writes the position (1, 2, ...) of the first active flag in the list, or 0
-- when none is active. Any failure writes 0 so the call keeps its normal routing.

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
        local flag_uuid = tostring(argv[position + 1])
        local flag = flag_state.load_flag_by_uuid(dbh, flag_uuid, domain_uuid)

        if flag and flag_state.is_active(dbh, flag) then
            match = position
            break
        end

        debug_log("NOTICE", string.format("Flag %d (%s) is inactive", position, flag_uuid))
        position = position + 1
    end

    dbh:release()

    debug_log("NOTICE", "First active flag position: " .. tostring(match))
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
