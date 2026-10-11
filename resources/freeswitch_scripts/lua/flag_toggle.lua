-- flag_toggle.lua
-- Switch a manual Flag on or off when a phone dials flag<number> (usually a BLF key).
-- Only extensions in this account may toggle. When the flag lists allowed
-- extensions, only those may toggle. An optional PIN is checked last.

require "resources.functions.config"

local Database = require "resources.functions.database"
local flag_state = require "lua.flag_state"

local api = freeswitch.API()

DEBUG_MODE = false -- Set to true to enable debug logs

local SCRIPT_NAME = "[flag_toggle.lua]"

local function debug_log(level, message)
    if DEBUG_MODE then
        freeswitch.consoleLog(level, SCRIPT_NAME .. " " .. tostring(message) .. "\n")
    end
end

local function refuse(dbh, reason)
    debug_log("NOTICE", "Refused: " .. reason)
    if dbh then dbh:release() end
    if session:ready() then
        session:streamFile("phrase:voicemail_fail_auth:#")
    end
    session:hangup("NORMAL_CLEARING")
end

local function check_pin(pin)
    pin = tostring(pin or "")
    if pin == "" then
        return true
    end

    for attempt = 1, 3 do
        local digits = session:playAndGetDigits(#pin, #pin, 1, 5000, "#", "phrase:voicemail_enter_pass:#", "", "\\d+")
        if tostring(digits) == pin then
            return true
        end
        debug_log("NOTICE", "Wrong PIN on attempt " .. attempt)
    end

    return false
end

local function caller_allowed(dbh, flag_uuid, domain_uuid, extension_uuid)
    -- The caller must be a registered extension in this account.
    if extension_uuid == "" then
        return false, "caller is not an extension"
    end

    local in_domain = dbh:first_value([[
        SELECT 1 FROM v_extensions
        WHERE extension_uuid = :extension_uuid AND domain_uuid = :domain_uuid
        LIMIT 1
    ]], { extension_uuid = extension_uuid, domain_uuid = domain_uuid })
    if not in_domain then
        return false, "extension is not in this account"
    end

    local allowed_count = tonumber(dbh:first_value(
        "SELECT count(*) FROM flag_extensions WHERE flag_uuid = :flag_uuid",
        { flag_uuid = flag_uuid }
    ) or 0)

    -- An empty list means any extension in the account may toggle.
    if allowed_count == 0 then
        return true
    end

    local listed = dbh:first_value([[
        SELECT 1 FROM flag_extensions
        WHERE flag_uuid = :flag_uuid AND extension_uuid = :extension_uuid
        LIMIT 1
    ]], { flag_uuid = flag_uuid, extension_uuid = extension_uuid })

    if listed then
        return true
    end

    return false, "extension is not on the allowed list"
end

local function main()
    if not session:ready() then return end

    session:answer()
    session:sleep(500)

    local domain_uuid = session:getVariable("domain_uuid") or ""
    local domain_name = session:getVariable("domain_name") or ""
    local destination_number = session:getVariable("destination_number") or ""
    local extension_uuid = session:getVariable("extension_uuid") or ""

    local flag_number = destination_number:match("^flag(%d+)$")
    if domain_uuid == "" or domain_name == "" or not flag_number then
        return refuse(nil, "missing domain or flag number (to=" .. destination_number .. ")")
    end

    local dbh = Database.new("system")
    if not (dbh and dbh:connected()) then
        return refuse(nil, "database unavailable")
    end

    local flag = dbh:first_row([[
        SELECT uuid, type, active, enabled, pin_number, blf_enabled
        FROM flags
        WHERE domain_uuid = :domain_uuid AND flag_number = :flag_number
        LIMIT 1
    ]], { domain_uuid = domain_uuid, flag_number = flag_number })

    if not flag then
        return refuse(dbh, "no flag " .. flag_number .. " in domain " .. domain_name)
    end

    if flag.type ~= "manual" then
        return refuse(dbh, "flag " .. flag_number .. " follows a schedule and cannot be switched by hand")
    end

    if not flag_state.truthy(flag.enabled) then
        return refuse(dbh, "flag " .. flag_number .. " is disabled")
    end

    local allowed, reason = caller_allowed(dbh, flag.uuid, domain_uuid, extension_uuid)
    if not allowed then
        return refuse(dbh, reason)
    end

    if not check_pin(flag.pin_number) then
        return refuse(dbh, "PIN check failed")
    end

    local new_active = not flag_state.truthy(flag.active)

    local ok = dbh:query([[
        UPDATE flags SET active = :active, updated_at = now()
        WHERE uuid = :uuid AND domain_uuid = :domain_uuid
    ]], { active = new_active and "true" or "false", uuid = flag.uuid, domain_uuid = domain_uuid })
    dbh:release()

    if ok == false then
        return refuse(nil, "update failed")
    end

    debug_log("NOTICE", string.format("Flag %s in %s switched %s", flag_number, domain_name, new_active and "on" or "off"))

    if flag_state.truthy(flag.blf_enabled) then
        api:execute("bgapi", string.format("luarun lua/flag_notify.lua %s %s %s",
            flag_number, domain_name, new_active and "true" or "false"))
    end

    if session:ready() then
        session:streamFile(new_active and "ivr/ivr-enabled.wav" or "ivr/ivr-disabled.wav")
    end

    session:hangup("NORMAL_CLEARING")
end

main()
