-- Send a uaCSTA SIP INFO event. FS PBX selects the registration and passes
-- explicit details so this script stays small and predictable.

local function b64decode(data)
    local alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/'
    data = tostring(data or ''):gsub('[^' .. alphabet .. '=]', '')

    return (data:gsub('.', function(char)
        if char == '=' then
            return ''
        end

        local index = alphabet:find(char, 1, true)
        if not index then
            return ''
        end

        index = index - 1
        local bits = ''

        for bit = 6, 1, -1 do
            bits = bits .. (index % 2 ^ bit - index % 2 ^ (bit - 1) > 0 and '1' or '0')
        end

        return bits
    end):gsub('%d%d%d?%d?%d?%d?%d?%d?', function(bits)
        if #bits ~= 8 then
            return ''
        end

        local char = 0
        for bit = 1, 8 do
            char = char + (bits:sub(bit, bit) == '1' and 2 ^ (8 - bit) or 0)
        end

        return string.char(char)
    end))
end

local function write_response(message)
    if stream and stream.write then
        stream:write(message)
    end
end

local profile = b64decode(argv[1])
local to_uri = b64decode(argv[2])
local from_uri = b64decode(argv[3])
local body = b64decode(argv[4])

if profile == '' or to_uri == '' or from_uri == '' or body == '' then
    freeswitch.consoleLog('ERR', '[uaCSTA] Usage: luarun lua/uacsta_makecall.lua <profile64> <to64> <from64> <body64>\n')
    write_response('-ERR missing uaCSTA argument\n')
    return
end

freeswitch.consoleLog('NOTICE', '[uaCSTA] Preparing MakeCall SIP INFO through profile ' .. profile .. ' to ' .. to_uri .. '\n')

local event = freeswitch.Event('SEND_INFO')
event:addHeader('profile', profile)
event:addHeader('to-uri', to_uri)
event:addHeader('from-uri', from_uri)
event:addHeader('content-type', 'application/csta+xml')
event:addHeader('content-disposition', 'signal;handling=required')
event:addHeader('content-length', tostring(#body))
event:addBody(body)
event:fire()

freeswitch.consoleLog('INFO', '[uaCSTA] Sent MakeCall SIP INFO through profile ' .. profile .. ' to ' .. to_uri .. '\n')
write_response('+OK uaCSTA SEND_INFO fired\n')
