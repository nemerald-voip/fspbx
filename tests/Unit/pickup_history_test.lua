package.path = './resources/freeswitch_scripts/?.lua;' .. package.path
local history = require 'resources.functions.pickup_history'
local json = require 'resources.functions.lunajson'
local target = '11111111-1111-4111-8111-111111111111'
local picker = '22222222-2222-4222-8222-222222222222'
local domain = '33333333-3333-4333-8333-333333333333'
local count = 0
local function test(code, account, failure, forwarded)
    local vars = {domain_uuid = domain, destination_number = code, caller_id_number = '304'}
    if forwarded then
        vars['sip_h_X-FSPBX-Pickup-Code'] = '*97302'
        vars['sip_h_X-FSPBX-Pickup-Ringing'] = '302'
    end
    local remote = {domain_uuid = account, destination_number = '302', start_uepoch = '1000000000'}
    local warnings = 0
    local api = {
        getTime = function() return '1003000' end,
        executeString = function(_, command)
            local id, key = command:match('^uuid_getvar (%S+) (%S+)$')
            if key then assert(id == target); return remote[key] or '_undef_' end
            local value
            id, key, value = command:match('^uuid_setvar (%S+) (%S+) (.+)$')
            assert(id == target)
            if failure and key == 'fspbx_pickup_events' then error('metadata write failed') end
            remote[key] = value
            return '+OK'
        end,
    }
    local session = {get_uuid = function() return picker end,
        getVariable = function(_, key) return vars[key] end,
        setVariable = function(_, key, value) vars[key] = value end}
    local permitted = history.prepare(session, api, target, not forwarded and '302' or nil, function() warnings = warnings + 1 end)
    count = count + 1
    return permitted, remote, vars, warnings, function() history.prepare(session, api, target, '302', function() end) end
end
for _, code in ipairs({'**302', '*97302', '*8', '**'}) do
    local ok, remote, vars, _, repeat_pickup = test(code, domain)
    assert(ok and remote.force_process_cdr == 'true')
    local event = json.decode(vars.fspbx_pickup_events)[1]
    assert(event.code == code and event.pickup_extension == '304' and event.ringing_extension == '302')
    assert(event.at == 1003000000)
    repeat_pickup()
    assert(#json.decode(remote.fspbx_pickup_events) == 2)
end
local ok, remote = test('**302', picker)
assert(not ok and not remote.force_process_cdr)
local ok, remote, _, warnings = test('**302', domain, true)
assert(ok and warnings == 1 and remote.force_process_cdr == 'true')
local _, _, vars = test('*97', domain)
assert(not vars.fspbx_pickup_events)
local ok, _, vars = test('*8', domain, false, true)
local event = json.decode(vars.fspbx_pickup_events)[1]
assert(ok and event.code == '*97302' and event.ringing_extension == '302')
print('PASS ' .. count .. ' pickup history scenarios')
