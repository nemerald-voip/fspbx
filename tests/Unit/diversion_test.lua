package.path = './resources/freeswitch_scripts/?.lua;' .. package.path
local logs = {}
freeswitch = { consoleLog = function(level, message) logs[#logs + 1] = message end }
local diversion = require 'resources.functions.diversion'
local account = '11111111-1111-4111-8111-111111111111'
local local_entry = '<sip:+12137577900@account.test>;reason=unconditional;counter=1'
local received = '"Smith, O\'Brien" <sip:+442079460958@previous.test>;reason=user-busy'

local function session(values)
    local s = { vars = values or {}, calls = {} }
    function s:getVariable(name) return self.vars[name] end
    function s:setVariable(name, value) self.vars[name] = value end
    function s:execute(app, data)
        self.calls[#self.calls + 1] = { app, data, self.vars.sip_h_Diversion, self.vars.export_vars }
        if self.fail then error('bridge failed') end
    end
    return s
end

-- Inbound capture/export is native XML and exercised by the isolated SIP lab.
local s = session({ domain_uuid = account, domain_name = 'account.test', sip_h_Diversion = received,
    original_did = '+12137577900', export_vars = 'original_did' })
assert(diversion.header(s) == local_entry .. ', ' .. received, 'The inbound DID qualifies without a ring-group marker.')
local exports = s.vars.export_vars
assert(diversion.apply(s))
assert(#s.calls == 0, 'The header helper must never execute a bridge.')
s:execute('bridge', 'sofia/gateway/primary/+18302660401')
s:execute('bridge', 'sofia/gateway/fallback/+18302660401')
assert(s.calls[1][3] == local_entry .. ', ' .. received)
assert(s.calls[2][3] == s.calls[1][3], 'Fallback must not append another entry.')
assert(diversion.apply(s))
assert(s.vars.sip_h_Diversion == local_entry .. ', ' .. received, 'Repeated preparation is idempotent.')
diversion.reset(s)
assert(s.vars.sip_h_Diversion == received and s.vars.export_vars == exports)
s:execute('bridge', 'user/1993@account.test')
assert(s.calls[3][3] == received, 'No generated header on internal calls.')
diversion.apply(s)
s.vars.export_vars = s.vars.export_vars .. ',another_export'
diversion.reset(s)
assert(s.vars.export_vars == exports .. ',another_export', 'Clear must preserve exports added by later actions.')
diversion.apply(s)
s.vars.sip_h_Diversion = '<sip:+12025550100@manual.test>'
diversion.reset(s)
assert(s.vars.sip_h_Diversion == '<sip:+12025550100@manual.test>', 'Leave later administrator overrides intact.')
s.vars.sip_h_Diversion = received
assert(diversion.is_gateway('{origination_caller_id_name=Test}[leg_timeout=20]sofia/gateway/gw/123'))
assert(not diversion.is_gateway('loopback/123'))
assert(not diversion.is_gateway('sofia/gateway/gw/123,user/1000'))
assert(not diversion.is_gateway('{sip_h_Diversion=custom}sofia/gateway/gw/123'))

local variables = diversion.loopback_variables(s)
local b = session({ domain_uuid = account, domain_name = 'account.test' })
for name, value in variables:gmatch('([^,=]+)=([^,]*),') do b.vars[name] = value end
assert(diversion.header(b) == local_entry .. ', ' .. received, 'Loopback carries the chain without dial-string quoting.')
variables = diversion.loopback_variables(b)
for name, value in variables:gmatch('([^,=]+)=([^,]*),') do b.vars[name] = value end
assert(diversion.header(b) == local_entry .. ', ' .. received, 'Nested groups retain one local entry.')
b.vars.sip_h_Diversion = local_entry .. ', ' .. received
assert(diversion.header(b) == b.vars.sip_h_Diversion)
assert(diversion.header(b, local_entry) == b.vars.sip_h_Diversion, 'Do not duplicate the local entry from a saved Bridge.')
b.vars.sip_h_Diversion = received .. ', ' .. received
assert(diversion.header(b) == local_entry .. ', ' .. received .. ', ' .. received, 'Preserve received entries, including repeated hops.')
b.vars.sip_h_Diversion = local_entry .. ', ' .. received
assert(diversion.header(b, '<sip:+61293744000@other.test>;reason=unconditional') ==
    local_entry .. ', ' .. received .. ', <sip:+61293744000@other.test>;reason=unconditional')
diversion.apply(b)
diversion.reset(b)
assert(diversion.header(b) == local_entry .. ', ' .. received, 'Clearing a generated header does not revoke inbound DID eligibility.')
assert(b.vars.original_did == '+12137577900')

for _, value in ipairs({ '1993', 'caller', '+12137577900\r\nInjected: true', '' }) do
    local invalid = session({ domain_uuid = account, domain_name = 'account.test', original_did = value })
    assert(diversion.header(invalid) == nil)
    assert(diversion.loopback_variables(invalid) == '')
end
for _, value in ipairs({ '<sip:123@host>\r\nBad: yes', '"unclosed <sip:123@host>', '<sip:123@host>, garbage', '<sip:${system(echo bad)}@host>' }) do
    s.vars.sip_h_Diversion = value
    assert(diversion.header(s) == nil)
end
s.vars.sip_h_Diversion = received
for _, host in ipairs({ 'account.test\r\nInjected: true', '${domain_name}', 'account.test,other', '' }) do
    assert(diversion.header(session({ original_did = '+12137577900', domain_name = host })) == nil)
end
local log_count = #logs
for _, ordinary in ipairs({ session(), session({ original_did = '' }), session({ sip_h_Diversion = received }) }) do
    local previous = ordinary.vars.sip_h_Diversion
    assert(not diversion.apply(ordinary))
    assert(ordinary.vars.sip_h_Diversion == previous, 'Outbound calls without an inbound DID keep their existing headers.')
end
assert(#logs == log_count, 'Ordinary outbound calls must not generate missing-DID warnings.')

-- The standalone entrypoint only prepares headers; native XML owns bridging.
_G.session = s
local calls = #s.calls
argv = {}
dofile('./resources/freeswitch_scripts/diversion.lua')
assert(#s.calls == calls and s.vars.sip_h_Diversion == local_entry .. ', ' .. received)
argv = { 'clear' }
dofile('./resources/freeswitch_scripts/diversion.lua')
assert(s.vars.sip_h_Diversion == received and s.vars.original_did == '+12137577900')

-- Run the saved Bridge resolver itself, including its custom SIP headers.
local saved = '<sip:+61293744000@saved.test>;reason=unconditional'
local endpoint = '{origination_caller_id_name=Company}sofia/gateway/saved/123'
package.loaded['resources.functions.database'] = { new = function() return {
    connected = function() return true end, release = function() end,
    query = function(_, sql, params, callback)
        assert(params.domain_uuid == account)
        callback({ bridge_destination = endpoint, header_name = 'X-Company', header_value = 'test' })
        callback({ bridge_destination = endpoint, header_name = 'Diversion', header_value = saved })
    end,
} end }
local opted_out = session({ domain_uuid = account, domain_name = 'account.test', sip_h_Diversion = received,
    original_did = '+12137577900', export_vars = 'original_did' })
_G.session, argv = opted_out, { '33333333-3333-4333-8333-333333333333' }
dofile('./resources/freeswitch_scripts/bridge.lua')
assert(opted_out.calls[1][2] == '{sip_h_X-Company=test,sip_h_Diversion=' .. saved .. ',origination_caller_id_name=Company}sofia/gateway/saved/123')
assert(opted_out.calls[1][3] == received and opted_out.vars.diversion_applied == nil,
    'Saved Bridges must retain existing behavior until the route enables diversion.lua.')
local bridge_session = session({ domain_uuid = account, domain_name = 'account.test', sip_h_Diversion = received,
    original_did = '+12137577900', export_vars = 'original_did' })
assert(diversion.apply(bridge_session))
_G.session, argv = bridge_session, { '33333333-3333-4333-8333-333333333333' }
dofile('./resources/freeswitch_scripts/bridge.lua')
local call = bridge_session.calls[1]
assert(call[2] == '{sip_h_X-Company=test,origination_caller_id_name=Company}sofia/gateway/saved/123')
assert(call[3] == local_entry .. ', ' .. received .. ', ' .. saved)
assert(bridge_session.vars.sip_h_Diversion == call[3])
diversion.apply(bridge_session)
assert(bridge_session.vars.sip_h_Diversion == local_entry .. ', ' .. received, 'Saved Bridge headers do not leak into a fallback gateway.')
endpoint = 'user/1000@account.test'
dofile('./resources/freeswitch_scripts/bridge.lua')
assert(bridge_session.calls[2][3] == received, 'Internal saved Bridges do not receive generated Diversion.')
assert(bridge_session.calls[2][2]:find('sip_h_Diversion=' .. saved, 1, true), 'Keep configured internal Bridge headers.')

local another = session({ domain_uuid = account, domain_name = 'account.test', original_did = '+442079460958' })
assert(diversion.header(another) == '<sip:+442079460958@account.test>;reason=unconditional;counter=1', 'A shared group uses each call\'s DID.')
print('Diversion helper tests passed')
