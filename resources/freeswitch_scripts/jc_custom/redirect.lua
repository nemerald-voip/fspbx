--include config.lua
        require "resources.functions.config";

--add the function
        require "resources.functions.explode";
        require "resources.functions.trim";
        require "resources.functions.channel_utils";

--prepare the api object
        api = freeswitch.API();

-- The flag parameter identifying destination support STIR/SHAKEN
local shakenString = ";shaken"

-- The option to insert Identity header per destination
local branchString = "[sip_h_Identity=${identity}]"
--local branchString = "[sip_h_X-Identity=${identity}]"


-- If no Identity header in INVITE, use SIP 3xx X-Identity header
if not session:getVariable("identity") then
session:setVariable("identity", session:getVariable("sip_rh_X-Identity"))
end

-- Extract destination dial string from SIP 3xx
local inDialString = session:getVariable("sip_redirect_dialstring")

-- Insert Identity header into SIP INVITE to destinations supporting SHAKEN
local outDialString = ""
-- For each destination
for part in string.gmatch(inDialString , "([^|]+)") do
if string.match(part, shakenString) then
-- If destination supports SHAKEN, insert Identity header
outDialString = outDialString .. branchString .. string.gsub(part, shakenString, "") .. "|"
else
-- If destination does not support SHAKEN, do nothing
outDialString = outDialString .. part .. "|"
end
end

-- Erase trailing pipe char
session:setVariable("redirect_dialstring", string.sub(outDialString, 1, -2))
