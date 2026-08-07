-- Announce a caller's live position in a mod_callcenter queue.
-- Arguments: caller UUID, queue name, and announcement frequency in seconds.

-- Enable/Disable debug mode globally
DEBUG_MODE = false  -- Set to false to disable debug logs

-- Debug logging function
function debug_log(level, message)
    if DEBUG_MODE then
        freeswitch.consoleLog(level, message .. "\n")
    end
end

local api = freeswitch.API()
local caller_uuid = argv[1]
local queue_name = argv[2]
local frequency_seconds = tonumber(argv[3])
local INITIAL_LOOKUP_TIMEOUT_MS = 5000
local INITIAL_LOOKUP_INTERVAL_MS = 100
local STOP_CHECK_INTERVAL_MS = 500

local function trim(value)
    return tostring(value or ""):match("^%s*(.-)%s*$")
end

local function valid_uuid(value)
    return type(value) == "string"
        and #value == 36
        and value:match("^%x%x%x%x%x%x%x%x%-%x%x%x%x%-%x%x%x%x%-%x%x%x%x%-%x%x%x%x%x%x%x%x%x%x%x%x$") ~= nil
end

local function valid_queue_name(value)
    return type(value) == "string"
        and value:match("^[%w%._%+%-]+@[%w%._%-]+$") ~= nil
end

local function api_argument(value)
    value = tostring(value or "")
        :gsub("\\", "\\\\")
        :gsub("'", "\\'")

    return "'" .. value .. "'"
end

local function queue_call_is_active()
    local cause = trim(api:executeString("uuid_getvar " .. caller_uuid .. " cc_cause"))

    if cause == "" or cause == "_undef_" then
        return true
    end

    if cause:sub(1, 4) == "-ERR" then
        debug_log("INFO", "[callcenter_announce_position.lua] Caller channel disappeared; stopping worker")
    else
        debug_log("INFO", "[callcenter_announce_position.lua] Caller left the queue with cause " .. cause .. "; stopping worker")
    end

    return false
end

local function sleep_while_active(milliseconds)
    local remaining = milliseconds

    while remaining > 0 do
        if not queue_call_is_active() then
            return false
        end

        local duration = math.min(remaining, STOP_CHECK_INTERVAL_MS)
        freeswitch.msleep(duration)
        remaining = remaining - duration
    end

    return queue_call_is_active()
end

local function split_row(line)
    local columns = {}

    for value in (line .. "|"):gmatch("(.-)|") do
        table.insert(columns, value)
    end

    return columns
end

local function current_position()
    local response = api:executeString("callcenter_config queue list members " .. queue_name)
    response = tostring(response or "")

    if response:find("%-ERR", 1) then
        debug_log("ERR", "[callcenter_announce_position.lua] FreeSWITCH could not list members for queue " .. queue_name)
        return nil, false
    end

    local headers = nil
    local session_uuid_column = nil
    local state_column = nil
    local position = 0

    for line in response:gmatch("[^\r\n]+") do
        local columns = split_row(line)

        if not headers and columns[1] == "queue" then
            headers = columns

            for index, name in ipairs(headers) do
                if name == "session_uuid" then
                    session_uuid_column = index
                elseif name == "state" then
                    state_column = index
                end
            end
        elseif headers and session_uuid_column and state_column then
            local state = columns[state_column]

            if state == "Waiting" or state == "Trying" then
                position = position + 1

                if columns[session_uuid_column] == caller_uuid then
                    return position, true
                end
            end
        end
    end

    return nil, true
end

local function broadcast(application)
    if not queue_call_is_active() then
        return false
    end

    local result = trim(api:executeString(
        "uuid_broadcast " .. caller_uuid .. " " .. api_argument(application) .. " aleg"
    ))

    if result:sub(1, 3) ~= "+OK" then
        debug_log("ERR", "[callcenter_announce_position.lua] Broadcast failed: " .. result)
        return false
    end

    return true
end

local function periodic_sound()
    local sound = trim(api:executeString("uuid_getvar " .. caller_uuid .. " cc_position_announce_sound"))

    if sound == "" or sound == "_undef_" or sound:sub(1, 4) == "-ERR" then
        return nil
    end

    return sound
end

local function announce_position(position, include_periodic_sound)
    if include_periodic_sound then
        local sound = periodic_sound()

        if sound and not broadcast("playback::" .. sound) then
            return false
        end
    end

    debug_log("INFO", "[callcenter_announce_position.lua] Announcing position " .. tostring(position) .. " in queue " .. queue_name)

    -- The phrase application expects <macro_name>,<data>. The double colon
    -- belongs to uuid_broadcast's application selector, not to phrase data.
    return broadcast("phrase::queue_position," .. tostring(position))
end

debug_log("INFO", "[callcenter_announce_position.lua] Starting caller position announcement worker")

if not valid_uuid(caller_uuid) then
    debug_log("ERR", "[callcenter_announce_position.lua] Invalid or missing caller UUID")
    return
end

if not valid_queue_name(queue_name) then
    debug_log("ERR", "[callcenter_announce_position.lua] Invalid or missing queue name")
    return
end

if not frequency_seconds
    or frequency_seconds < 1
    or frequency_seconds > 86400
    or frequency_seconds ~= math.floor(frequency_seconds) then
    debug_log("ERR", "[callcenter_announce_position.lua] Announcement frequency must be a whole number from 1 through 86400")
    return
end

local initial_wait = 0
local position = nil

while initial_wait < INITIAL_LOOKUP_TIMEOUT_MS do
    if not queue_call_is_active() then
        return
    end

    local response_valid
    position, response_valid = current_position()

    if not response_valid then
        return
    end

    if position then
        break
    end

    freeswitch.msleep(INITIAL_LOOKUP_INTERVAL_MS)
    initial_wait = initial_wait + INITIAL_LOOKUP_INTERVAL_MS
end

if not position then
    debug_log("INFO", "[callcenter_announce_position.lua] Caller did not enter queue " .. queue_name .. "; stopping worker")
    return
end

if not announce_position(position, false) then
    return
end

local frequency_milliseconds = frequency_seconds * 1000

while sleep_while_active(frequency_milliseconds) do
    local response_valid
    position, response_valid = current_position()

    if not response_valid or not position then
        debug_log("INFO", "[callcenter_announce_position.lua] Caller is no longer a waiting member of queue " .. queue_name .. "; stopping worker")
        return
    end

    if not announce_position(position, true) then
        return
    end
end
