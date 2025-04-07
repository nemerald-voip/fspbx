-- Enable/Disable debug mode globally
DEBUG_MODE = false  -- Set to false to disable debug logs

-- Debug logging function
function debug_log(level, message)
    if DEBUG_MODE then
        freeswitch.consoleLog(level, message .. "\n")
    end
end

-- Emergency Notify Script (emergency_notify.lua)
debug_log("INFO", "[emergency_notify.lua] Executing Emergency Notify Lua Script...")

-- Set the sound path dynamically
session:setVariable("sound_prefix", "/var/www/fspbx/resources/sounds/en/us/alloy/emergency_notify")

-- Answer the call
session:answer()

-- Sleep a moment to make sure audio is ready
session:sleep(1000)

-- Say the emergency greeting
session:streamFile("emergency_greeting.wav")

session:execute("say", "en number iterated 101")

session:sleep(300)

-- Say the emergency message
session:streamFile("dialed_emergency_number.wav")

session:sleep(1000)
-- Hangup after message
session:hangup()