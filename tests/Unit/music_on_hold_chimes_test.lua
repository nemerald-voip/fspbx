-- Run from the repository root with lua tests/Unit/music_on_hold_chimes_test.lua.
package.path = './resources/freeswitch_scripts/?.lua;' .. package.path
debug, expire = {}, {}
sounds_dir, recordings_dir = '/sounds', '/recordings'
local voice = '/sounds/en/us/callie/ivr/'
local saved = '/recordings/support.test/notice & hold.wav'
local sounds = { [saved] = true, [voice .. '8000/hello.wav'] = true, [voice .. '16000/hello.wav'] = true }
function file_exists(path) return sounds[path] and path end
freeswitch = { getGlobalVariable = function() return nil end }
package.loaded['resources.functions.cache'] = { get = function() end, set = function() return true end }

local function render(list, rate, domain)
    local row = {
        music_on_hold_path = '/music', music_on_hold_name = 'Support',
        music_on_hold_rate = rate, domain_name = domain, music_on_hold_chime_list = list,
    }
    package.loaded['resources.functions.database'] = { new = function() return {
        connected = function() return true end, release = function() end,
        query = function(_, _, callback) callback(row) end,
    } end }
    dofile('resources/freeswitch_scripts/app/xml_handler/resources/scripts/configuration/local_stream.conf.lua')
    return XML_STRING:match('<param name="chime%-list" value="([^"]*)"/>')
end

local escaped = '/recordings/support.test/notice &amp; hold.wav'
for _, rate in ipairs({'8000', '16000'}) do
    local stock = voice .. rate .. '/hello.wav'
    assert(render('ivr/hello.wav, notice & hold.wav, ,ivr/hello.wav,' .. saved, rate, 'support.test')
        == table.concat({stock, escaped, stock, escaped}, ','))
    assert(render(saved, rate, 'support.test') == escaped)
    assert(render('notice & hold.wav', rate, 'other.test') == 'notice &amp; hold.wav')
    assert(render('notice & hold.wav', rate, nil) == 'notice &amp; hold.wav')
    assert(render(nil, rate) == nil and render(' , ', rate) == nil)
end
print('Music on Hold chime XML checks passed.')
