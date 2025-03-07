-- Enable/Disable debug mode globally
DEBUG_MODE = false  -- Set to false to disable debug logs

-- Debug logging function
function debug_log(level, message)
    if DEBUG_MODE then
        freeswitch.consoleLog(level, message .. "\n")
    end
end

-- Wakeup Call Script (wakeup.lua)
debug_log("INFO", "[wakeup_call.lua] Executing Wakeup Call Lua Script...")

-- Get wakeup call UUID
local uuid = session:getVariable("wakeup_call_uuid")
if not uuid or uuid == "0" then
    freeswitch.consoleLog("WARNING", "[wakeup_call.lua] No Wakeup Call ID provided.\n")
    return
end

debug_log("INFO", "[wakeup_call.lua] Wakeup Call ID: " .. uuid)

-- Set the sound path dynamically
session:setVariable("sound_prefix", "/var/www/fspbx/resources/sounds/en/us/alloy/wakeup")

-- Answer the call
session:answer()
session:sleep(1000) -- Short delay to prevent audio clipping

-- Prompt user for input
local min_digits = 1
local max_digits = 1
local max_attempts = 3
local timeout = 5000  -- 5 seconds
local sound_greeting = "wakeup_greeting.wav"
local invalid_sound = "invalid_option.wav"

-- Get user input
local user_input = session:playAndGetDigits(min_digits, max_digits, max_attempts, timeout, "#", sound_greeting, invalid_sound, "[12345]")

if user_input == "1" or user_input == "2" or user_input == "3" or user_input == "4" then
    -- Snooze Handling
    local snooze_time = tonumber(user_input) == 1 and 5 or
                        tonumber(user_input) == 2 and 10 or
                        tonumber(user_input) == 3 and 20 or
                        tonumber(user_input) == 4 and 30 or nil

    if snooze_time then
        debug_log("INFO", "[wakeup_call.lua] Snoozing for " .. snooze_time .. " minutes")

        -- Run Laravel Artisan command for snooze
        local cmd = "/usr/bin/php /var/www/fspbx/artisan wakeup:snooze " .. uuid .. " " .. snooze_time .. " &"
        os.execute(cmd)

        debug_log("INFO", "[wakeup_call.lua] Executed: " .. cmd)

        -- Play snooze confirmation sound
        session:streamFile("snooze_confirm.wav")
    end

elseif user_input == "5" or not user_input or user_input == "" then
    -- Wakeup Confirmation
    debug_log("INFO", "[wakeup_call.lua] Confirming Wakeup Call ID: " .. uuid)

    -- Run Laravel Artisan command for confirmation
    os.execute("/usr/bin/php /var/www/fspbx/artisan wakeup:confirm " .. uuid .. " &")

    -- Play exit message
    session:streamFile("exit.wav")
end

session:sleep(1000)
session:hangup()
