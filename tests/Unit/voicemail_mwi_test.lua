-- Run from the repository root with Lua. All FreeSWITCH calls are mocked.
-- FreeSWITCH test runs may supply an absolute script root as argv[1].
local root = (argv and argv[1]) or 'resources/freeswitch_scripts/'
local output = stream and function(message) stream:write(message .. '\n') end or print
package.path = root .. '?.lua;' .. package.path
package.loaded['resources.functions.config'] = true
local events = {}
freeswitch = {
    consoleLog = function() end,
    API = function() return {
        executeString = function(_, command)
            assert(command == 'sofia_contact */100@example.test')
            -- Multiple contacts on the same profile must receive one MWI event.
            return 'sofia/internal/100@example.test,sofia/internal/100@example.test'
        end,
        execute = function(_, command)
            assert(command == 'create_uuid')
            return '00000000-0000-4000-8000-000000000711'
        end,
    } end,
    Event = function(name)
        local event = {name = name:upper(), headers = {}}
        function event:addHeader(key, value) self.headers[key] = value end
        function event:fire() events[#events + 1] = self end
        return event
    end,
}

local tests = 0
local function check(new_count, saved_count, omitted)
    events = {}
    argv = omitted and {'100', 'example.test'}
        or {'100', 'example.test', tostring(new_count), tostring(saved_count)}
    dofile(root .. 'app/voicemail/resources/scripts/mwi_notify.lua')

    assert(#events == 3, 'Expected one MWI event and two BLF presence events')
    local waiting = new_count > 0 and 'yes' or 'no'
    local state = new_count > 0 and 'confirmed' or 'terminated'
    local counts = string.format('%d/%d (0/0)', new_count, saved_count)
    assert(events[1].name == 'MESSAGE_WAITING')
    assert(events[1].headers['MWI-Message-Account'] == 'sip:100@example.test')
    assert(events[1].headers['sofia-profile'] == 'internal')
    assert(events[1].headers['MWI-Messages-Waiting'] == waiting, 'Incorrect waiting flag for ' .. counts)
    assert(events[1].headers['MWI-Voice-Message'] == counts, 'Incorrect message counts for ' .. counts)
    assert(events[2].name == 'PRESENCE_OUT')
    assert(events[3].name == 'PRESENCE_IN')
    for index = 2, 3 do
        assert(events[index].headers['from'] == 'vm100@example.test')
        assert(events[index].headers['answer-state'] == state, 'Incorrect BLF state for ' .. counts)
        assert(events[index].headers['status'] == string.format('Active (%d waiting)', new_count))
    end
    tests = tests + 1
    output('PASS wrapper MWI/BLF ' .. counts .. (omitted and ' (default counts)' or ''))
end

check(1, 0)
check(3, 7)
check(0, 7)
check(0, 0)
check(0, 0, true)
output(tests .. ' voicemail MWI regression tests passed')
