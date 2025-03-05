-- Wakeup Call Script (wakeup.lua)
freeswitch.consoleLog("INFO", "[wakeup_call.lua] Executing Wakeup Call Lua Script...\n")

-- Set the sound path dynamically
session:setVariable("sound_prefix", "/var/www/fspbx/resources/sounds/en/us/alloy/wakeup")

-- Answer the call
session:answer()
session:sleep(1000) -- Short delay to prevent audio clipping

-- Initialize the IVR Menu
local menu = freeswitch.IVRMenu(
    nil,                    -- No parent menu
    "wakeup_ivr",           -- Menu name
    "wakeup_greeting.wav",  -- Initial greeting
    "wakeup_options.wav",   -- Short repeat greeting
    "invalid_option.wav",   -- Invalid entry prompt
    "exit.wav",             -- Exit sound
    nil,                    -- No transfer sound
    nil,                    -- No confirm macro
    "#",                    -- Confirmation key (not used)
    nil,                    -- No TTS engine
    nil,                    -- No TTS voice
    3,                      -- Max confirmation attempts
    5000,                   -- Inter-digit timeout (5 sec)
    1,                      -- Max digit length (1 digit)
    5000,                   -- Timeout before looping (5 sec)
    3,                      -- Max failures before exit
    3                       -- Max timeouts before exit
)

-- Define actions
menu:bindAction("menu-exec-app", "lua lua/wakeup_snooze.lua 5", "1")   -- Snooze for 5 min
menu:bindAction("menu-exec-app", "lua lua/wakeup_snooze.lua 10", "2")  -- Snooze for 10 min
menu:bindAction("menu-exec-app", "lua lua/wakeup_snooze.lua 20", "3")  -- Snooze for 20 min
menu:bindAction("menu-exec-app", "lua lua/wakeup_snooze.lua 30", "4")  -- Snooze for 30 min
-- Confirm wake-up and run the Lua script to execute Artisan command
menu:bindAction("menu-exec-app", "lua lua/wakeup_confirm.lua", "5")

-- Execute the IVR menu
menu:execute(session, "wakeup_ivr")

session:sleep(1000)
session:hangup()
