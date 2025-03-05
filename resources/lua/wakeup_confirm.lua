local uuid = session:getVariable("wakeup_call_uuid")

if uuid == "0" then
    freeswitch.consoleLog("WARNING", "[wakeup_call.lua] No Wakeup Call ID provided.\n")
    return
end

freeswitch.consoleLog("INFO", "[wakeup_call.lua] Confirming Wakeup Call ID: " .. uuid .. "\n")

-- Run Laravel Artisan command
os.execute("/usr/bin/php /var/www/fspbx/artisan wakeup:confirm " .. uuid .. " &")

session:streamFile("exit.wav")
session:sleep(1000)
session:hangup()
