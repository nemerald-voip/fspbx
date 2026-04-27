-- flow_toggle.lua
-- Toggle Call Flow and notify BLF subscribers (flow<ext>@domain).

require "resources.functions.config"
require "resources.functions.channel_utils"

local cache     = require "resources.functions.cache"
local Database  = require "resources.functions.database"
local play_file = require "resources.functions.play_file"

local api = freeswitch.API()

DEBUG_MODE = false -- Set to true to enable debug logs

local SCRIPT_NAME = "[flow_toggle.lua]"

-- Debug logging function
local function debug_log(level, message)
    if DEBUG_MODE then
        freeswitch.consoleLog(level, SCRIPT_NAME .. " " .. tostring(message) .. "\n")
    end
end

local function check_pin(pin)
    pin = tostring(pin or "")

    if pin == "" then
        return true
    end

    local min_digits = #pin
    local max_digits = #pin
    local max_tries = 3
    local timeout = 5000

    for attempt = 1, max_tries do
        debug_log("NOTICE", string.format("PIN attempt %s of %s", attempt, max_tries))

        local digits = session:playAndGetDigits(
            min_digits,          -- min digits
            max_digits,          -- max digits
            1,                   -- tries per prompt
            timeout,             -- timeout ms
            "#",                 -- terminator
            "phrase:voicemail_enter_pass:#",
            "",                  -- invalid file
            "\\d+"               -- digits only
        )

        debug_log("NOTICE", "Entered PIN: " .. tostring(digits))

        if tostring(digits) == pin then
            debug_log("NOTICE", "PIN accepted")
            return true
        end
    end

    debug_log("ERR", "PIN validation failed")
    session:streamFile("phrase:voicemail_fail_auth:#")
    session:hangup("NORMAL_CLEARING")
    return false
end

local function play_call_flow_sound(dbh, row, new_status, domain_name, domain_uuid)
    local audio_file = ""

    if tostring(new_status) == "true" then
        audio_file = tostring(row.call_flow_sound or "")
    else
        audio_file = tostring(row.call_flow_alternate_sound or "")
    end

    if audio_file == "" then
        debug_log("NOTICE", "No call flow sound defined for status=" .. tostring(new_status))

        if session:ready() then
            session:sleep(2000)
        end

        return
    end

    debug_log("NOTICE", "Playing call flow audio for status=" .. tostring(new_status) .. ": " .. tostring(audio_file))

    if session:ready() then
        session:sleep(1000)

        play_file(dbh, domain_name, domain_uuid, audio_file)

        session:sleep(1000)
    end
end

local function main()
    if not session:ready() then return end

    session:answer()
    session:sleep(1000)
    if not session:ready() then return end

    -- Pull all the important vars from the channel
    local domain_uuid = session:getVariable("domain_uuid")
    local domain_name = session:getVariable("domain_name")
    local destination_number = session:getVariable("destination_number") or ""

    debug_log("NOTICE", string.format(
        "Flow Toggle: to=%s domain_uuid=%s domain_name=%s",
        tostring(destination_number),
        tostring(domain_uuid),
        tostring(domain_name)
    ))

    if not (domain_uuid and domain_name and destination_number) then
        debug_log("ERR", "Missing required session variables (domain_uuid/domain_name/destination_number)")
        session:hangup()
        return
    end

    local extension = destination_number:match("^flow(%d+)$")
    local fc = destination_number:match("^(%*%d+)$")

    local lookup_column
    local lookup_value

    if extension then
        lookup_column = "call_flow_extension"
        lookup_value = extension
    elseif fc then
        lookup_column = "call_flow_feature_code"
        lookup_value = fc
    else
        debug_log("ERR", "Could not extract extension or feature code from destination_number: " .. tostring(destination_number))
        session:hangup()
        return
    end

    debug_log("NOTICE", "Extracted extension: " .. tostring(extension))
    debug_log("NOTICE", "Extracted feature code: " .. tostring(fc))
    debug_log("NOTICE", "Call flow lookup: " .. lookup_column .. "=" .. tostring(lookup_value))

    local dbh = Database.new("system")
    if not (dbh and dbh:connected()) then
        debug_log("ERR", "DB connect failed (system)")
        session:hangup()
        return
    end

    local sql = string.format([[
        SELECT *
        FROM v_call_flows
        WHERE %s = :lookup_value
          AND domain_uuid = :domain_uuid
        LIMIT 1
    ]], lookup_column)

    local params = {
        domain_uuid  = domain_uuid,
        lookup_value = lookup_value,
    }

    local row = dbh:first_row(sql, params)
    if not row then
        debug_log("ERR", string.format(
            "Call flow not found for %s=%s domain_uuid=%s",
            lookup_column,
            tostring(lookup_value),
            tostring(domain_uuid)
        ))
        dbh:release()
        session:hangup()
        return
    end

    -- Log all retrieved row values for debugging
    -- for key, value in pairs(row) do
    --     debug_log("NOTICE", string.format("row[%s] = %s", tostring(key), tostring(value)))
    -- end

    local pin_number = tostring(row.call_flow_pin_number or "")

    if not check_pin(pin_number) then
        dbh:release()
        return
    end

    local current_status = tostring(row.call_flow_status or "true")
    if current_status == "" then
        current_status = "true"
    end

    local toggle = (current_status == "true") and "false" or "true"

    debug_log("NOTICE", string.format(
        "Toggling call flow %s from %s to %s",
        tostring(row.call_flow_uuid),
        tostring(current_status),
        tostring(toggle)
    ))

    local update_sql = [[
        UPDATE v_call_flows
        SET call_flow_status = :toggle
        WHERE call_flow_uuid = :call_flow_uuid
    ]]

    local update_params = {
        toggle = toggle,
        call_flow_uuid = row.call_flow_uuid,
    }

    local ok = dbh:query(update_sql, update_params)

    if ok == false then
        debug_log("ERR", "Failed to update call_flow_status for call_flow_uuid=" .. tostring(row.call_flow_uuid))
        dbh:release()
        session:hangup()
        return
    end

    debug_log("NOTICE", "Call flow status updated successfully to " .. tostring(toggle))

    -- Send BLF notify BEFORE playing the sound.
    local notify_target = tostring(row.call_flow_extension or extension or lookup_value)

    local cmd = string.format(
        "luarun lua/flow_notify.lua %s %s %s",
        notify_target,
        domain_name,
        toggle
    )

    debug_log("NOTICE", "Sending BLF notify command: " .. cmd)
    api:execute("bgapi", cmd)


    if session:ready() then
        play_call_flow_sound(dbh, row, toggle, domain_name, domain_uuid)
    end

    dbh:release()
    session:hangup()
    return
end

main()
