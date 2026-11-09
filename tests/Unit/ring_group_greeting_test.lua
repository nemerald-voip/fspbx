-- Run from the repository root with Lua 5.2+. No live calls or database access.
package.path = 'resources/freeswitch_scripts/?.lua;' .. package.path

for _, name in ipairs({
    'explode', 'base64', 'file_exists', 'channel_utils', 'format_ringback',
    'send_presence', 'route_to_bridge', 'diversion',
}) do
    package.loaded['resources.functions.' .. name] = {}
end

local greeting, events
local finished = {}
package.loaded['resources.functions.database'] = {new = function()
    return {query = function(_, sql, params, callback)
        if sql:find('FROM v_ring_groups as r, v_ring_group_users as u', 1, true) then
            -- Stop after the greeting, before destination lookup or dialing.
            error(finished)
        end
        assert(sql:find('FROM v_ring_groups as r, v_domains as d', 1, true))
        callback({
            domain_uuid = 'account', domain_name = 'example.test',
            ring_group_extension = '9000', ring_group_greeting = greeting,
        })
        return true
    end}
end}
package.loaded['resources.functions.play_file'] = function(_, _, _, filename)
    events[#events + 1] = 'play:' .. filename
end
freeswitch = {
    API = function() return {execute = function() return 'test-host' end} end,
    consoleLog = function() end,
}
session = {
    ready = function() return true end,
    setHangupHook = function() end,
    setAutoHangup = function() end,
    getVariable = function(_, key)
        return ({ring_group_uuid = 'group', recordings_dir = '/recordings', uuid = 'call'})[key]
    end,
    setVariable = function() end,
    execute = function(_, application) events[#events + 1] = application end,
    answer = function() events[#events + 1] = 'answer' end,
    sleep = function(_, milliseconds) events[#events + 1] = 'sleep:' .. milliseconds end,
}

for _, case in ipairs({
    {name = 'NULL greeting'},
    {name = 'empty greeting', value = ''},
    {name = 'legacy None selection', value = '0'},
    {name = 'recording', value = 'welcome.wav', plays = true},
    {name = 'recording named zero', value = '0.wav', plays = true},
}) do
    greeting, events = case.value, {}
    local ok, failure = pcall(dofile, 'resources/freeswitch_scripts/app/ring_groups/index.lua')
    assert(not ok and failure == finished, tostring(failure))
    local expected = case.plays
        and 'ring_ready,answer,sleep:1000,play:' .. case.value .. ',sleep:1000'
        or 'ring_ready'
    assert(table.concat(events, ',') == expected, case.name .. ': ' .. table.concat(events, ','))
    print('PASS ' .. case.name)
end
