-- flow_toggle.lua
-- Toggle Call Flow and notify BLF subscribers (flow<ext>@domain).

require "resources.functions.config"
require "resources.functions.channel_utils"

local cache    = require "resources.functions.cache"
local Database = require "resources.functions.database"

local api = freeswitch.API()

DEBUG_MODE = false -- Set to true to enable debug logs

local SCRIPT_NAME = "[flow_toggle.lua]"

-- Debug logging function
local function debug_log(level, message)
    if DEBUG_MODE then
        freeswitch.consoleLog(level, SCRIPT_NAME .. " " .. tostring(message) .. "\n")
    end
end

local function file_exists(path)
    local f = io.open(path, "rb")
    if f then
        f:close()
        return true
    end

    return false
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
            min_digits,
            max_digits,
            1,
            timeout,
            "#",
            "phrase:voicemail_enter_pass:#",
            "",
            "\\d+"
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

local function play_call_flow_sound(row, new_status, domain_name, sounds_dir, default_language, default_dialect, default_voice)
    local sound_file = ""

    if tostring(new_status) == "true" then
        sound_file = tostring(row.call_flow_sound or "")
    else
        sound_file = tostring(row.call_flow_alternate_sound or "")
    end

    if sound_file == "" then
        debug_log("NOTICE", "No call flow sound defined for status=" .. tostring(new_status))
        return
    end

    local domain_sound_path =
        "/var/lib/freeswitch/recordings/" ..
        tostring(domain_name) ..
        "/" ..
        sound_file

    if file_exists(domain_sound_path) then
        debug_log("NOTICE", "Playing domain call flow sound: " .. domain_sound_path)
        session:streamFile(domain_sound_path)
        return
    end

    local system_base_path =
        tostring(sounds_dir) ..
        "/" ..
        tostring(default_language) ..
        "/" ..
        tostring(default_dialect) ..
        "/" ..
        tostring(default_voice)

    -- First try the simple/direct system path.
    local system_sound_path = system_base_path .. "/" .. sound_file

    if file_exists(system_sound_path) then
        debug_log("NOTICE", "Playing system call flow sound: " .. system_sound_path)
        session:streamFile(system_sound_path)
        return
    end

    -- Then try FusionPBX/FreeSWITCH sample-rate subdirectory layout.
    -- Example:
    -- sound_file = ivr/ivr-night_mode.wav
    -- path       = /usr/share/freeswitch/sounds/en/us/callie/ivr/8000/ivr-night_mode.wav
    local sound_dir, sound_name = sound_file:match("^(.-)/([^/]+)$")

    if sound_dir and sound_name then
        local rates = { "8000", "16000", "32000", "48000" }

        for _, rate in ipairs(rates) do
            local rated_system_sound_path =
                system_base_path ..
                "/" ..
                sound_dir ..
                "/" ..
                rate ..
                "/" ..
                sound_name

            if file_exists(rated_system_sound_path) then
                debug_log("NOTICE", "Playing rated system call flow sound: " .. rated_system_sound_path)
                session:streamFile(rated_system_sound_path)
                return
            end
        end
    end

    debug_log("ERR", "Sound file not found. Checked domain path: " .. domain_sound_path)
    debug_log("ERR", "Sound file not found. Checked system path: " .. system_sound_path)

    if sound_dir and sound_name then
        debug_log("ERR", "Also checked rated system paths under: " .. system_base_path .. "/" .. sound_dir .. "/{8000,16000,32000,48000}/" .. sound_name)
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

    local sounds_dir = session:getVariable("sounds_dir") or "/usr/share/freeswitch/sounds"
    local default_language = session:getVariable("default_language") or "en"
    local default_dialect = session:getVariable("default_dialect") or "us"
    local default_voice = session:getVariable("default_voice") or "callie"

    debug_log("NOTICE", string.format(
        "Flow Toggle: to=%s domain_uuid=%s domain_name=%s sounds=%s/%s/%s/%s",
        tostring(destination_number),
        tostring(domain_uuid),
        tostring(domain_name),
        tostring(sounds_dir),
        tostring(default_language),
        tostring(default_dialect),
        tostring(default_voice)
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
        domain_uuid = domain_uuid,
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
        play_call_flow_sound(
            row,
            toggle,
            domain_name,
            sounds_dir,
            default_language,
            default_dialect,
            default_voice
        )
    end

    dbh:release()
    session:hangup()
    return
end

main()
