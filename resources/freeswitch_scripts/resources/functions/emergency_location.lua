local M = {}

-- Percent-encode UTF-8 bytes, not just spaces. Never interpolate user text into a channel expression.
function M.encode(location)
    if type(location) ~= 'string' then return nil end
    if location:find('[%z\1-\31\127$]') then return nil end
    location = location:match('^%s*(.-)%s*$')
    if location == '' or #location > 240 then return nil end
    local encoded = location:gsub('([^A-Za-z0-9._~%-])', function(byte)
        return string.format('%%%02X', string.byte(byte))
    end)
    return encoded
end

return M
