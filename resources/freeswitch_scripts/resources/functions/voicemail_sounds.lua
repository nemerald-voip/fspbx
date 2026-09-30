local M = {}

local function value(session, name, fallback)
    local selected = session:getVariable(name)
    if selected == nil or selected == '' then return fallback end
    return selected
end

-- Phrase macros and native say otherwise replace the channel's sound_prefix
-- with the voice hardcoded in languages/<language>/<language>.xml. Use the
-- same language/dialect/voice as voicemail's direct file playback, for the
-- duration of voicemail only. Restore the caller's settings on errors too.
function M.with_voice(session, callback)
    local previous_prefix = session:getVariable('sound_prefix')
    local previous_enforced = session:getVariable('sound_prefix_enforced')
    local prefix = value(session, 'sounds_dir', '/usr/share/freeswitch/sounds'):gsub('/+$', '')
        .. '/' .. value(session, 'default_language', 'en')
        .. '/' .. value(session, 'default_dialect', 'us')
        .. '/' .. value(session, 'default_voice', 'callie')

    session:setVariable('sound_prefix', prefix)
    session:setVariable('sound_prefix_enforced', 'true')
    local ok, result = pcall(callback)
    session:setVariable('sound_prefix', previous_prefix)
    session:setVariable('sound_prefix_enforced', previous_enforced)
    if not ok then error(result, 0) end
    return result
end

return M
