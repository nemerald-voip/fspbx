freeswitch.consoleLog("INFO", "[wakeup_call.lua] Snoozing for " .. argv[1] .. " minutes\n")

local snooze_time = tonumber(argv[1])
local uuid = session:getVariable("wakeup_call_uuid")

freeswitch.consoleLog("INFO", "[wakeup_call.lua] UUID is " .. uuid .. "\n")

if uuid and snooze_time then
    -- Construct the command
    local cmd = "/usr/bin/php /var/www/fspbx/artisan wakeup:snooze " .. uuid .. " " .. snooze_time .. " &"

    -- Execute the Laravel command
    os.execute(cmd)

    freeswitch.consoleLog("INFO", "[wakeup.lua] Executed: " .. cmd .. "\n")
end

-- Set the sound directory
-- local sound_prefix = "/var/www/fspbx/storage/sounds/en/us/alloy/wakeup/"
session:streamFile("snooze_confirm.wav")
session:sleep(1000)
session:hangup()
