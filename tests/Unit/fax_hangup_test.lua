-- Run from the repository root: lua tests/Unit/fax_hangup_test.lua
package.path = './resources/freeswitch_scripts/?.lua;' .. package.path
package.loaded['resources.functions.config'] = true
local json = require 'resources.functions.lunajson'
local count = 0

local function run(overrides, legacy)
    local headers = {
        uuid = 'call', domain_uuid = 'account', domain_name = 'account.test',
        call_direction = 'outbound', fax_uuid = 'server',
        outbound_fax_uuid = 'fax', outbound_fax_attempt_uuid = 'attempt',
        fax_file = '/tmp/fax.tif', answer_epoch = '0',
        hangup_cause = 'NORMAL_TEMPORARY_FAILURE', hangup_cause_q850 = '41',
    }
    if legacy then
        headers.outbound_fax_uuid = nil
        headers.outbound_fax_attempt_uuid = nil
    end
    for key, value in pairs(overrides) do headers[key] = value end
    env = { getHeader = function(_, name) return headers[name] end }
    local payloads = {}
    freeswitch = {
        consoleLog = function() end,
        API = function() return {
            executeString = function(_, command)
                local payload = assert(command:match("^luarun lua/send_webhook.lua '(.*)'$"))
                payloads[#payloads + 1] = json.decode(payload)
                return '+OK'
            end,
        } end,
    }
    dofile('./resources/freeswitch_scripts/lua/fax_hangup.lua')
    count = count + 1
    return payloads
end

-- A rejected gateway is intermediate: the routing leg can try a fallback.
assert(#run({ originating_leg_uuid = 'routing-leg' }) == 0)
assert(#run({ originating_leg_uuid = 'routing-leg', answer_epoch = '123' }) == 0)

-- Failed routing reports once from B; A must not duplicate the event.
local events = run({ is_loopback = '1', loopback_leg = 'B',
    hangup_cause = 'NORMAL_CLEARING', hangup_cause_q850 = '16',
    originate_disposition = 'NORMAL_TEMPORARY_FAILURE' })
assert(#events == 1 and events[1].event == 'fax.sent')
local data = events[1].data
assert(data.outbound_fax_uuid == 'fax' and data.outbound_fax_attempt_uuid == 'attempt')
assert(data.domain_uuid == 'account' and data.fax_file == '/tmp/fax.tif')
assert(data.fax_success == '0' and data.fax_result_text == 'NORMAL_TEMPORARY_FAILURE')
assert(data.hangup_cause_q850 == '' and data.fax_result_code == '')
assert(#run({ is_loopback = 'true', loopback_leg = 'A' }) == 0)

-- Loopback bowout hands the attempt to the gateway, without an early failure.
for _, leg in ipairs({'A', 'B'}) do
    assert(#run({ is_loopback = '1', loopback_leg = leg, loopback_hangup_cause = 'bowout' }) == 0)
end
assert(#run({ is_loopback = '1', loopback_leg = 'B', answer_epoch = '123' }) == 0)

-- The selected gateway reports success or transmission failure.
events = run({ originating_leg_uuid = 'routing-leg', current_application = 'txfax',
    fax_success = '1', fax_result_code = '0', fax_result_text = 'OK',
    fax_document_transferred_pages = '2' })
assert(#events == 1 and events[1].data.fax_success == '1')
assert(events[1].data.fax_document_transferred_pages == '2')
events = run({ originating_leg_uuid = 'routing-leg', last_app = 'txfax',
    fax_success = '0', fax_result_code = '49', fax_result_text = 'Transmission failed' })
assert(#events == 1 and events[1].data.fax_result_code == '49')

-- Direct local sends and legacy/inbound fax hooks keep their existing behavior.
events = run({})
assert(#events == 1 and events[1].data.fax_result_code == '41')
events = run({ call_direction = 'inbound', fax_success = '1' }, true)
assert(#events == 1 and events[1].event == 'fax.received')
events = run({ originating_leg_uuid = 'legacy-origin' }, true)
assert(#events == 1 and events[1].event == 'fax.sent')
assert(#run({ is_loopback = '1', loopback_leg = 'B' }, true) == 0)

print('PASS ' .. count .. ' fax hangup scenarios')
