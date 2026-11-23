-- Run through run-inbound-destination-lab.sh; no application DB or live calls.
package.path = './resources/freeswitch_scripts/?.lua;' .. package.path
local Json = require 'resources.functions.lunajson'
local Destination = require 'resources.functions.inbound_destination'
local lab = assert(os.getenv('FSPBX_INBOUND_LAB'))
assert(lab:match('^/tmp/fspbx%-inbound%.[%w]+$'))
local assertions = 0
local function check(value, message)
    assertions = assertions + 1
    assert(value, message)
end
local function read(path)
    local file = assert(io.open(path))
    local value = file:read('*a')
    file:close()
    return value
end
local function probe(operation, input)
    local path = lab .. '/request.json'
    local file = assert(io.open(path, 'w'))
    file:write(Json.encode(input))
    file:close()
    local process = assert(io.popen('php tests/Unit/inbound_destination_probe.php ' .. operation .. ' ' .. path))
    local output = process:read('*a')
    assert(process:close(), output)
    return Json.decode(output)
end
local fixtures = Json.decode(read(lab .. '/fixtures.json'))
local regex_results = Json.decode(read(lab .. '/regex.json'))
local cache_values, queries, fail_query = {}, 0, false
local cache = {
    get = function(key)
        if cache_values[key] then return cache_values[key] end
        return nil, 'NOT FOUND'
    end,
    set = function(key, value) cache_values[key] = value; return true end,
}
local dbh = {}
function dbh:query(sql, params, callback)
    queries = queries + 1
    if fail_query then return false, 'simulated database outage' end
    for _, row in ipairs(probe('query', {sql = sql, params = params})) do callback(row) end
    return true
end
function dbh:connected() return true end
function dbh:release() end
local api = {}
function api:execute(command, value)
    if command == 'regex' then return regex_results[value] or probe('regex', value) end
    if command == 'url_decode' then
        return (value:gsub('%%(%x%x)', function(hex) return string.char(tonumber(hex, 16)) end))
    end
    if command == 'md5' then
        local hash = 7
        for index = 1, #value do hash = (hash * 31 + value:byte(index)) % 4294967296 end
        return string.rep(string.format('%08x', hash), 4)
    end
    if command == 'hostname' then return 'pbx.test' end
    error('Unexpected API command: ' .. command)
end
local function resolve(context, mode, request, recipient, fallback)
    return Destination.resolve(dbh, cache, api, context, mode, 'pbx.test', request, recipient, fallback, 60)
end
local function contains(routes, id)
    for _, route in ipairs(routes) do if route == id then return true end end
    return false
end
for _, fixture in ipairs(fixtures) do
    for _, mode in ipairs({'single', 'multiple'}) do
        for _, number in ipairs(fixture.accepted) do
            local selected, routes = resolve(fixture.context, mode, number, nil)
            check(selected == number and contains(routes, fixture.id), fixture.name .. ' ' .. mode .. ' ' .. number)
            local before = queries
            check(resolve(fixture.context, mode, number, nil) == number, 'Cached match changed')
            check(before == queries, 'Positive cache missed')
            if mode == 'single' then
                local params, found = {hostname = 'pbx.test'}, false
                dbh:query(Destination.single_dialplan_sql(routes, params), params, function(row)
                    if row.dialplan_xml:find('name="case', 1, true) then found = true end
                    check(not row.dialplan_xml:find('global%-3'), 'Non-public global route leaked')
                end)
                check(found, 'Single mode discarded the selected number')
            end
        end
        for _, number in ipairs(fixture.rejected) do
            check(resolve(fixture.context, mode, number, nil) == '', 'Unexpected match: ' .. number)
        end
    end
end
local france, usa = fixtures[1], fixtures[6]
check(resolve(france.context, 'multiple', '0123456789', 'invalid') == '0123456789', 'Request-URI priority')
check(resolve(france.context, 'multiple', 'unknown', '0123456789') == '0123456789', 'To fallback')
check(resolve(france.context, 'multiple', '%2B33123456789', 'invalid') == '+33123456789', 'URL decoding')
check(resolve(france.context, 'multiple', nil, nil, '0123456789') == '0123456789', 'Non-SIP fallback')
check(resolve(france.context, 'multiple', 'wrong', nil, '0123456789') == '', 'SIP call used unrelated fallback')
check(resolve('public@empty', 'multiple', '0123456789', nil) == '', 'Context isolation')
check(resolve(france.context, 'single', '0123456789', '+15304792220') == '0123456789', 'Single mode header priority')
local _, overlapping = resolve(usa.context, 'single', '5304792220', nil)
check(contains(overlapping, usa.id) and contains(overlapping, fixtures[11].id), 'Exact match suppressed overlapping range')
for offset = 0, 5 do
    for _, mode in ipairs({'single', 'multiple'}) do
        check(resolve('public', mode, '1999555990' .. offset, nil) == '', 'Account/host/enabled/context scope trap ' .. offset)
    end
end
cache_values, queries = {}, 0
check(resolve('public', 'multiple', 'unknown', 'unknown') == '', 'Unknown number')
check(queries == 2, 'Duplicate header queried more than once')
check(resolve('public', 'multiple', 'unknown', 'unknown') == '' and queries == 2, 'Negative cache missed')
for key in pairs(cache_values) do cache_values[key] = 'broken-json' end
resolve('public', 'multiple', 'unknown', 'unknown')
check(queries == 4, 'Invalid cache was trusted')
cache_values, fail_query = {}, true
local ok = pcall(resolve, france.context, 'multiple', '0123456789', '+33123456789')
check(not ok and next(cache_values) == nil, 'Database failure was cached as a miss')
fail_query = false

-- Query budgets include both headers; they never scale with the number of DIDs.
for _, mode in ipairs({'single', 'multiple'}) do
    cache_values, queries = {}, 0
    check(resolve(france.context, mode, 'unknown-request', 'unknown-to') == '', 'Unknown headers matched')
    check(queries == 3, 'Cold two-header lookup exceeded three database queries')
    local before = queries
    resolve(france.context, mode, 'unknown-request', 'unknown-to')
    check(queries == before, 'Cached call performed selection queries')
end
cache_values, queries = {}, 0
resolve(usa.context, 'multiple', '5304792220', nil)
check(queries == 1, 'Common carrier format lost the literal fast path')
cache_values, queries = {}, 0
resolve(france.context, 'multiple', 'unknown-request', '0123456789')
check(queries == 2, 'To fallback repeated the route-list query')

-- Execute the actual XML handler with the isolated DB and cache adapters.
package.loaded['resources.functions.cache'] = cache
package.loaded['resources.functions.database'] = {new = function() return dbh end}
package.loaded['resources.functions.log'] = {xml_handler = {notice = function() end}}
_G.api = api
_G.trim = function(value) return value:match('^%s*(.-)%s*$') end
_G.freeswitch = {consoleLog = function() end}
_G.debug = {}
_G.expire = {dialplan = 60}
local function xml_for(mode, context, request, recipient)
    cache_values['dialplan:mode'] = mode
    _G.call_context, _G.sip_req_user, _G.sip_to_user = context, request, recipient
    _G.destination_number = request
    dofile('resources/freeswitch_scripts/app/xml_handler/resources/scripts/dialplan/dialplan.lua')
    check(_G.sip_req_user == request and _G.sip_to_user == recipient, 'Original SIP headers changed')
    return _G.XML_STRING
end
for _, mode in ipairs({'single', 'multiple'}) do
    cache_values = {}
    local xml = xml_for(mode, france.context, '0123456789', 'other')
    check(xml:find('fspbx_inbound_did=0123456789', 1, true), 'Selected DID missing from XML')
    check(select(2, xml:gsub('name="case1"', '')) == 1, 'Route missing or duplicated in XML')
    for key, value in pairs(cache_values) do
        if key:find('dialplan:', 1, true) and value:find('<document', 1, true) then
            check(not value:find('fspbx_inbound_did=', 1, true), 'Per-call number leaked into XML cache')
        end
    end
    local second = xml_for(mode, france.context, '+33123456789', 'other')
    check(second:find('fspbx_inbound_did=+33123456789', 1, true), 'Cached XML retained previous call number')
end
cache_values = {}
local internal = xml_for('multiple', 'account.test', '19995559905', nil)
check(not internal:find('fspbx_inbound_did=', 1, true), 'Internal transfer was rewritten')
cache_values = {['dialplan:mode'] = 'single'}
fail_query = true
check(not pcall(xml_for, 'single', france.context, '0123456789', nil), 'Failed XML query was accepted')
for key in pairs(cache_values) do check(key == 'dialplan:mode', 'Failed XML was cached') end
print('Inbound Lua/PostgreSQL checks passed: ' .. assertions .. ' assertions')
