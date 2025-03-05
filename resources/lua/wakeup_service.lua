-- Wakeup Call Service (wakeup_service.lua)
freeswitch.consoleLog("INFO", "[wakeup_service.lua] Executing Wakeup Call Service Lua Script...\n")

-- Get user extension from session
local extension = session:getVariable("caller_id_number")
freeswitch.consoleLog("INFO", "[wakeup_service.lua] Extension " .. extension .."\n")

local extension_uuid = session:getVariable("extension_uuid")
freeswitch.consoleLog("INFO", "[wakeup_service.lua] Extension " .. extension_uuid .."\n")

-- Database connection
local Database = require "resources.functions.database";
dbh = Database.new('system');

freeswitch.consoleLog("INFO", "[wakeup_service.lua] Checking scheduled/snoozed wake-up calls for extension " .. extension_uuid .. "\n")

-- Query wake-up calls with status 'scheduled' or 'snoozed'
local sql = string.format([[
    SELECT wake_up_time, next_attempt_at, recurring, status, retry_count 
    FROM wakeup_calls 
    WHERE extension_uuid = '%s' 
    AND status IN ('scheduled', 'snoozed')
]], extension_uuid)

local has_wakeup_call = false

dbh:query(sql, function(row)
    has_wakeup_call = true
    freeswitch.consoleLog("INFO", string.format(
        "[wakeup_service.lua] Found wake-up call: Time: %s, Next Attempt: %s, Recurring: %s, Status: %s, Retries: %s\n",
        row.wake_up_time, row.next_attempt_at or "NULL", row.recurring, row.status or "NULL", row.retry_count
    ))
end)

if not has_wakeup_call then
    freeswitch.consoleLog("INFO", "[wakeup_service.lua] No scheduled or snoozed wake-up calls found for this extension.\n")
end





-- Function to schedule a wake-up call in the database
function schedule_wake_up_call(extension, time, date, repeat_daily)
    local repeat_value = (repeat_daily == "1") and "yes" or "no"
    local sql = string.format(
        "INSERT INTO wakeup_calls (extension, wakeup_time, wakeup_date, repeat_daily) VALUES ('%s', '%s', '%s', '%s') ON CONFLICT (extension) DO UPDATE SET wakeup_time='%s', wakeup_date='%s', repeat_daily='%s'",
        extension, time, date, repeat_value, time, date, repeat_value
    )
    dbh:query(sql)
end

-- Function to cancel a wake-up call
function cancel_wake_up_call(extension)
    local sql = string.format("DELETE FROM wakeup_calls WHERE extension='%s'", extension)
    dbh:query(sql)
end

-- Main IVR Menu
local main_menu = freeswitch.IVRMenu(nil, "wake_up_menu",
    "ivr/ivr-welcome.wav",     -- Greeting sound
    "ivr/ivr-please_choose.wav", -- Short greeting sound
    "ivr/ivr-invalid.wav",     -- Invalid entry sound
    "ivr/ivr-goodbye.wav",     -- Exit sound
    nil,                       -- Transfer sound
    nil,                       -- Confirm macro
    "#",                       -- Confirm key
    nil,                       -- TTS engine
    nil,                       -- TTS voice
    3,                         -- Confirm attempts
    3000,                      -- Inter-digit timeout
    1,                         -- Max number of digits
    5000,                      -- Timeout before looping
    3,                         -- Max failures before hanging up
    3)                         -- Max timeouts before hanging up

-- Bind menu options
main_menu:bindAction("menu-sub", "set_wakeup", "1")   -- Set a new wake-up call
main_menu:bindAction("menu-sub", "modify_wakeup", "2") -- Modify an existing wake-up call
main_menu:bindAction("menu-sub", "cancel_wakeup", "3") -- Cancel a wake-up call
main_menu:bindAction("menu-exit", nil, "9")            -- Exit IVR

-- Set Wake-Up Call Menu
local set_wakeup = freeswitch.IVRMenu(main_menu, "set_wakeup",
    "ivr/ivr-enter_time.wav", -- "Enter time in 24-hour format, e.g., 0730 for 7:30 AM."
    nil, "ivr/ivr-invalid.wav", nil, nil, nil, "#", nil, nil, 3, 3000, 4, 5000, 3, 3)
set_wakeup:bindAction("menu-sub", "set_date", "XXXX") -- Capture input and move to date selection

-- Date Selection Menu
local set_date = freeswitch.IVRMenu(set_wakeup, "set_date",
    "ivr/ivr-today_tomorrow_other.wav", -- "Press 1 for today, 2 for tomorrow, 3 for specific date."
    nil, "ivr/ivr-invalid.wav", nil, nil, nil, "#", nil, nil, 3, 3000, 1, 5000, 3, 3)
set_date:bindAction("menu-sub", "repeat_option", "1") -- Today
set_date:bindAction("menu-sub", "repeat_option", "2") -- Tomorrow
set_date:bindAction("menu-sub", "enter_date", "3")    -- Specific date

-- Enter Specific Date
local enter_date = freeswitch.IVRMenu(set_date, "enter_date",
    "ivr/ivr-enter_date.wav", -- "Enter date in MMDD format."
    nil, "ivr/ivr-invalid.wav", nil, nil, nil, "#", nil, nil, 3, 3000, 4, 5000, 3, 3)
enter_date:bindAction("menu-sub", "repeat_option", "XXXX") -- Capture date and move to repeat selection

-- Repeat Option Menu
local repeat_option = freeswitch.IVRMenu(set_date, "repeat_option",
    "ivr/ivr-repeat_daily.wav", -- "Press 1 to repeat daily, or 2 for one-time."
    nil, "ivr/ivr-invalid.wav", nil, nil, nil, "#", nil, nil, 3, 3000, 1, 5000, 3, 3)
repeat_option:bindAction("menu-exec-app", "lua save_wakeup.lua", "1") -- Save with repeat daily
repeat_option:bindAction("menu-exec-app", "lua save_wakeup.lua", "2") -- Save as one-time

-- Modify Wake-Up Call Menu
local modify_wakeup = freeswitch.IVRMenu(main_menu, "modify_wakeup",
    "ivr/ivr-enter_new_time.wav", -- "Enter the new time in 24-hour format."
    nil, "ivr/ivr-invalid.wav", nil, nil, nil, "#", nil, nil, 3, 3000, 4, 5000, 3, 3)
modify_wakeup:bindAction("menu-sub", "set_date", "XXXX") -- Move to date selection

-- Cancel Wake-Up Call Menu
local cancel_wakeup = freeswitch.IVRMenu(main_menu, "cancel_wakeup",
    "ivr/ivr-confirm_cancel.wav", -- "Press 1 to confirm cancellation."
    nil, "ivr/ivr-invalid.wav", nil, nil, nil, "#", nil, nil, 3, 3000, 1, 5000, 3, 3)
cancel_wakeup:bindAction("menu-exec-app", "lua cancel_wakeup.lua", "1") -- Cancel wake-up call

-- Execute Main Menu
main_menu:execute(session, "wake_up_menu")

-- Release DB connection
dbh:release()
