freeswitch.consoleLog("INFO", "[notify_webhook.lua] Executing Notify Webhook Service Lua Script...\n")
-- Enable/Disable debug mode globally
DEBUG_MODE = true  -- Set to false to disable debug logs

-- Debug logging function
function debug_log(level, message)
    if DEBUG_MODE then
        freeswitch.consoleLog(level, message .. "\n")
    end
end

local payload = argv[1] or ""
local url = "http://127.0.0.1/webhook/freeswitch"
local secret = "tH0FXyxfG6Kh36*VHYdE4G!gwfE3Pf"

if payload == "" then
    debug_log("ERR", "Payload required!\n")
    return
end

-- Generate the signature using openssl
local handle = io.popen(string.format("echo -n '%s' | openssl dgst -sha256 -hmac '%s' | sed 's/^.* //'", payload, secret))
local signature = handle:read("*a"):gsub("%s+", "")  -- remove any trailing newlines
handle:close()

-- Build the curl command
local cmd = string.format(
    "curl -k -s -X POST -H 'Content-Type: application/json' -H 'Signature: %s' -d '%s' '%s'",
    signature, payload, url
)

-- Execute the curl command
debug_log("NOTICE", "[notify_webhook.lua] CMD: " .. cmd .. "\n")
local result = os.execute(cmd)
debug_log("NOTICE", "[notify_webhook.lua] Result: " .. tostring(result) .. "\n")



debug_log("INFO", "[notify_webhook.lua] END Notify Webhook Service Lua Script...\n")