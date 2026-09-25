package.path = 'resources/freeswitch_scripts/?.lua;' .. package.path
local sounds = require 'resources.functions.voicemail_sounds'
local tests = 0

local function test(name, run)
    run()
    tests = tests + 1
    print('PASS ' .. name)
end

local function make_session(variables)
    return {
        getVariable = function(_, name) return variables[name] end,
        setVariable = function(_, name, value) variables[name] = value end,
    }
end

test('German voicemail phrases use piper instead of the XML callie voice', function()
    local variables = {
        default_language = 'de', default_dialect = 'de', default_voice = 'piper',
        sound_prefix = '/custom/previous', sound_prefix_enforced = 'false',
    }
    local session = make_session(variables)
    local result = sounds.with_voice(session, function()
        -- Model the FreeSWITCH phrase/say rule: XML overrides the prefix
        -- unless sound_prefix_enforced is true.
        local playback_prefix = variables.sound_prefix_enforced == 'true'
            and variables.sound_prefix or '/usr/share/freeswitch/sounds/de/de/callie'
        assert(playback_prefix == '/usr/share/freeswitch/sounds/de/de/piper')
        return '123'
    end)
    assert(result == '123')
    assert(variables.sound_prefix == '/custom/previous')
    assert(variables.sound_prefix_enforced == 'false')
end)

test('regional packs and custom sound roots keep their exact directory names', function()
    for _, pack in ipairs({{'es', 'mx', 'maria'}, {'es', '419', 'custom'}, {'pt', 'BR', 'karina'}, {'fr', 'ca', 'june'}, {'zh', 'tw', 'custom'}}) do
        local variables = {
            sounds_dir = '/srv/sounds/', default_language = pack[1],
            default_dialect = pack[2], default_voice = pack[3],
        }
        sounds.with_voice(make_session(variables), function()
            assert(variables.sound_prefix == '/srv/sounds/' .. table.concat(pack, '/'))
            assert(variables.sound_prefix_enforced == 'true')
        end)
        assert(variables.sound_prefix == nil and variables.sound_prefix_enforced == nil)
    end
end)

test('missing and empty selections use the same English defaults', function()
    for _, variables in ipairs({{}, {sounds_dir = '', default_language = '', default_dialect = '', default_voice = ''}}) do
        sounds.with_voice(make_session(variables), function()
            assert(variables.sound_prefix == '/usr/share/freeswitch/sounds/en/us/callie')
        end)
    end
end)

test('playback failure restores an explicitly enforced caller prefix', function()
    local variables = {sound_prefix = '/custom/caller', sound_prefix_enforced = 'true'}
    local ok, failure = pcall(function()
        sounds.with_voice(make_session(variables), function() error('fixture playback failure') end)
    end)
    assert(not ok and failure:find('fixture playback failure', 1, true))
    assert(variables.sound_prefix == '/custom/caller')
    assert(variables.sound_prefix_enforced == 'true')
end)

test('voicemail entry point selects the voice before running and restores it on failure', function()
    local variables = {default_language = 'de', default_dialect = 'de', default_voice = 'piper'}
    session = make_session(variables)
    argv = {}
    package.loaded['resources.functions.database'] = {new = function()
        assert(variables.sound_prefix == '/usr/share/freeswitch/sounds/de/de/piper')
        assert(variables.sound_prefix_enforced == 'true')
        error('fixture database failure')
    end}
    local ok, failure = pcall(dofile, 'resources/freeswitch_scripts/app/voicemail/index.lua')
    assert(not ok and failure:find('fixture database failure', 1, true))
    assert(variables.sound_prefix == nil and variables.sound_prefix_enforced == nil)
    session = nil
end)

print(tests .. ' voicemail sound tests passed')
