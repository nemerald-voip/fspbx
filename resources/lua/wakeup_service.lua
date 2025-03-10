-- Wakeup Call Service (wakeup_service.lua)
freeswitch.consoleLog("INFO", "[wakeup_service.lua] Executing Wakeup Call Service Lua Script...\n")

-- Enable/Disable debug mode globally
DEBUG_MODE = true  -- Set to false to disable debug logs

-- Debug logging function
function debug_log(level, message)
    if DEBUG_MODE then
        freeswitch.consoleLog(level, message)
    end
end

-- Function to determine the correct year for the wake-up call
function determine_wakeup_year(current_year, current_month, requested_month)
    if requested_month < current_month then
        return current_year + 1
    else
        return current_year
    end
end

-- Function to schedule a wake-up call
function schedule_wake_up_call(domain_uuid, extension_uuid, wake_up_date, wake_up_time, recurring, action, existing_wakeup_uuid)
    local is_recurring = (recurring == "1") -- Convert "1" → true, "2" → false

    -- Get switch time zone from FreeSWITCH variables
    local switch_tz = session:getVariable("timezone") or "UTC" -- Default to UTC if not set
    debug_log("INFO", "[wakeup_service.lua] Switch Time Zone: " .. switch_tz .. "\n")

    -- Ensure wake_up_date is in MMDD format and convert it to YYYY-MM-DD
    local requested_month, requested_day = wake_up_date:match("^(%d%d)(%d%d)$")
    local current_date = os.date("*t")  -- Get the current date
    local current_year = current_date.year
    local current_month = current_date.month

    if not requested_month or not requested_day then
        debug_log("ERR", "[wakeup_service.lua] Invalid date format: " .. wake_up_date .. "\n")
        return
    end

    -- Convert month and day to numbers
    requested_month = tonumber(requested_month)
    requested_day = tonumber(requested_day)

    -- Determine the correct year
    local wakeup_year = determine_wakeup_year(current_year, current_month, requested_month)
    local formatted_date = string.format("%04d-%02d-%02d", wakeup_year, requested_month, requested_day)

    -- Ensure wake_up_time is properly formatted as HH:MM
    local wake_up_hour, wake_up_min = wake_up_time:match("^(%d%d)(%d%d)$")
    if not wake_up_hour or not wake_up_min then
        debug_log("ERR", "[wakeup_service.lua] Invalid time format: " .. wake_up_time .. "\n")
        return
    end
    local formatted_time = wake_up_hour .. ":" .. wake_up_min

    -- Combine date and time into a full local timestamp
    local local_datetime = string.format("%s %s:00", formatted_date, formatted_time)

    -- Convert local time to UTC using os.date()
    local year, month, day, hour, min = string.match(local_datetime, "(%d+)-(%d+)-(%d+) (%d+):(%d+):%d+")

    if not year or not month or not day or not hour or not min then
        debug_log("ERR", "[wakeup_service.lua] Failed to parse date-time: " .. local_datetime .. "\n")
        return
    end

    local local_epoch = os.time({
        year = tonumber(year),
        month = tonumber(month),
        day = tonumber(day),
        hour = tonumber(hour),
        min = tonumber(min)
    })

    -- Step 2: Adjust to UTC (account for system time zone offset)
    local utc_epoch = local_epoch - os.difftime(os.time(), os.time(os.date("!*t", os.time())))
    local utc_datetime = os.date("!%Y-%m-%d %H:%M:00", local_epoch)

    debug_log("INFO", "[wakeup_service.lua] Local Wake-Up Time: " .. local_datetime .. "\n")
    debug_log("INFO", "[wakeup_service.lua] UTC Wake-Up Time: " .. utc_datetime .. "\n")

    if existing_wakeup_uuid then
        -- Update existing wake-up call
        local sql_update = string.format([[
            UPDATE wakeup_calls 
            SET wake_up_time = '%s', next_attempt_at = '%s', recurring = %s, status = 'scheduled', retry_count = 0, updated_at = NOW()
            WHERE extension_uuid = '%s'
        ]], utc_datetime, utc_datetime, is_recurring and "TRUE" or "FALSE", extension_uuid)
    
        debug_log("INFO", "[wakeup_service.lua] Updating wake-up call: " .. sql_update .. "\n")
        dbh:query(sql_update)
    else
        -- Insert new wake-up call
        local sql_insert = string.format([[
            INSERT INTO wakeup_calls ( domain_uuid, extension_uuid, wake_up_time, next_attempt_at, recurring, status, retry_count, created_at, updated_at)
            VALUES ('%s', '%s', '%s', '%s', %s,'scheduled', 0, NOW(), NOW())
        ]], domain_uuid, extension_uuid, utc_datetime, utc_datetime, is_recurring and "TRUE" or "FALSE")
    
        debug_log("INFO", "[wakeup_service.lua] Inserting wake-up call: " .. sql_insert .. "\n")
        dbh:query(sql_insert)
    end
end

-- Function to cancel a wake-up call
function cancel_wake_up_call(extension_uuid)
    local sql = string.format("UPDATE wakeup_calls SET status='canceled', updated_at=NOW() WHERE extension_uuid='%s'", extension_uuid)
    dbh:query(sql)
    debug_log("INFO", "[wakeup_service.lua] Wake-up call for extension " .. extension_uuid .. " has been marked as canceled.\n")
end

-- Function to handle new or modify wake-up call
function handle_wakeup_call(session, domain_uuid, extension_uuid, action, existing_wakeup_uuid)
    -- Prompt user for time
    local entered_time = session:playAndGetDigits(4, 4, 3, 5000, "#", 
        "wakeup_service_enter_time.wav", "invalid_time.wav", "^([01][0-9]|2[0-3])[0-5][0-9]$", 'entered_time')
    debug_log("INFO", "[wakeup_service.lua] User entered wake-up time: " .. (entered_time or 'None') .. "\n")

    -- Prompt user for date
    local entered_date = session:playAndGetDigits(4, 4, 3, 5000, "#", 
        "wakeup_service_enter_date.wav", "invalid_date.wav", "^(0[1-9]|1[0-2])(0[1-9]|[12][0-9]|3[01])$", 'entered_date')
    debug_log("INFO", "[wakeup_service.lua] User entered wake-up date: " .. (entered_date or 'None') .. "\n")

    -- Prompt user for repeat option
    local wakeup_recurring = session:playAndGetDigits(1, 1, 3, 5000, "#", 
        "wakeup_service_repeat_daily.wav", "invalid_option.wav", "^[12]$", 'wakeup_recurring')
    debug_log("INFO", "[wakeup_service.lua] User selected recurring option: " .. (wakeup_recurring or 'None') .. "\n")

    -- Debug existing call ID
    debug_log("INFO", "[wakeup_service.lua] Existing wake-up call ID: " .. (existing_wakeup_uuid or 'None') .. "\n")

    -- Only schedule if all required inputs are present
    if entered_time and entered_time ~= "" and 
       entered_date and entered_date ~= "" and 
       wakeup_recurring and wakeup_recurring ~= "" then
        schedule_wake_up_call(domain_uuid, extension_uuid, entered_date, entered_time, wakeup_recurring, action, existing_wakeup_uuid)
    else
        debug_log("ERR", "[wakeup_service.lua] Missing required parameters, wake-up call not scheduled.\n")
        return
    end

    -- **Play Confirmation Greeting**
    if session:ready() then
        session:streamFile("wakeup_service_succesfully_scheduled.wav")
        debug_log("INFO", "[wakeup_service.lua] Played wake-up confirmation message.\n")
    end
end


-- MAIN SCRIPT
-- Set the sound path dynamically
session:setVariable("sound_prefix", "/var/www/fspbx/resources/sounds/en/us/alloy/wakeup")

-- Get user extension from session
local extension = session:getVariable("caller_id_number")
local domain_uuid = session:getVariable("domain_uuid")
local extension_uuid = session:getVariable("extension_uuid")

-- Answer the call
session:answer()
session:sleep(1000) -- Short delay to prevent audio clipping

-- Play a welcome message
session:streamFile("wakeup_service_welcome.wav")
session:sleep(500)

-- Determine if this is an internal call (caller’s own extension) or remote (for another extension)
local call_type = argv[1] or "internal"
debug_log("INFO", "[wakeup_service.lua] Call type: " .. (call_type or 'None') .. "\n")

-- Database connection
local Database = require "resources.functions.database";
dbh = Database.new('system');

if call_type == "remote" then
    extension_uuid = nil
    local max_attempts = 3
    local attempt = 0
    while attempt < max_attempts do
        local extension = session:playAndGetDigits(3, 6, 1, 5000, "#", 
            "wakeup_service_enter_target_extension.wav", "wakeup_service_invalid_extension.wav", "^[0-9]+$")
        debug_log("INFO", "[wakeup_service.lua] User entered target extension: " .. (extension or "None") .. "\n")
        
        if extension and extension ~= "" then
            local sql = string.format("SELECT extension_uuid FROM v_extensions WHERE extension = '%s' AND domain_uuid = '%s'", extension, domain_uuid)
            debug_log("INFO", "[wakeup_service.lua] SQL query: " .. sql .. "\n")
            
            dbh:query(sql, function(row)
                extension_uuid = row.extension_uuid
                debug_log("INFO", "[wakeup_service.lua] Found target extension UUID: " .. (extension_uuid or "None") .. "\n")
            end)
            
            if extension_uuid then
                break
            else
                attempt = attempt + 1
                session:streamFile("wakeup_service_invalid_extension.wav")
            end
        else
            attempt = attempt + 1
            -- session:streamFile("wakeup_service_invalid_extension.wav")
        end
    end

    if not extension_uuid then
        session:streamFile("thank_you_for_using_wakeup_service.wav")
        session:hangup()
    end
end


debug_log("INFO", "[wakeup_service.lua] Checking wake-up calls for extension " .. extension_uuid .. "\n")

-- Query wake-up calls with status 'scheduled' or 'snoozed'
local sql = string.format([[
    SELECT uuid, wake_up_time, next_attempt_at, recurring, status, retry_count 
    FROM wakeup_calls 
    WHERE extension_uuid = '%s' 
]], extension_uuid)

local has_wakeup_call = false
local existing_wakeup_uuid = nil  -- Store UUID of existing wake-up call

dbh:query(sql, function(row)
    existing_wakeup_uuid = row.uuid  -- Capture the UUID
    if row.status == 'scheduled' or row.status == 'snoozed' then
        has_wakeup_call = true
    end
    debug_log("INFO", string.format(
        "[wakeup_service.lua] Found wake-up call: UUID: %s, Time: %s, Next Attempt: %s, Recurring: %s, Status: %s, Retries: %s\n",
        row.uuid, row.wake_up_time, row.next_attempt_at or "NULL", row.recurring, row.status or "NULL", row.retry_count
    ))
end)

-- Determine the IVR greeting message
local greeting_message
local greeting_message_short
if has_wakeup_call then
    greeting_message = "wakeup_service_menu_existing.wav" 
    main_menu_regex = '[1230]'
else
    debug_log("INFO", "[wakeup_service.lua] No scheduled or snoozed wake-up calls found for this extension.\n")
    greeting_message = "wakeup_service_menu_new.wav" 
    main_menu_regex = '[10]'
end

-- Main Menu
local action = session:playAndGetDigits(1, 1, 3, 5000, "#", greeting_message, "invalid_option.wav", main_menu_regex, 'action')
debug_log("INFO", "[wakeup_service.lua] User selected action: " .. (action or 'None') .. "\n")

if action == "1" then
    -- New wake-up call
    handle_wakeup_call(session, domain_uuid, extension_uuid, "new", existing_wakeup_uuid)

elseif action == "2" and has_wakeup_call then
    -- Modify an existing wake-up call
    handle_wakeup_call(session, domain_uuid, extension_uuid, "modify", existing_wakeup_uuid)

elseif action == "3" and has_wakeup_call then
    -- Cancel a wake-up call
    cancel_wake_up_call(extension_uuid)
    session:streamFile("wakeup_call_canceled.wav")

elseif  action == "0" then
    session:streamFile("thank_you_for_using_wakeup_service.wav")
    session:hangup()
end


-- Release DB connection
dbh:release()



