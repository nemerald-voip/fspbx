package.path = "resources/freeswitch_scripts/?.lua;" .. package.path
local tracking = require "agent_call_track"
local warnings = {}
local function warn(message) warnings[#warnings + 1] = message end
local agent = "aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa"
local domain = "bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb"
local extension = "cccccccc-cccc-4ccc-cccc-cccccccccccc"
local tests = 0
local function test(name, callback)
    warnings = {}
    callback()
    tests = tests + 1
    print("PASS " .. name)
end
local function lookup(rows, failure)
    return {query = function(_, sql, params, callback)
        assert(sql:find("domain_uuid = :domain_uuid", 1, true))
        assert(params.domain_uuid == domain and params.contact == "user/201@example.test")
        if failure == "throw" then error("database offline") end
        if failure then return nil, "query failed" end
        for _, row in ipairs(rows) do callback(row) end
        return true
    end}
end
local function resolve(rows, failure)
    return tracking.resolve(lookup(rows, failure), domain, "example.test", "201", warn)
end
local function row(contact, kind)
    return {call_center_agent_uuid = agent, agent_contact = contact or "user/201@example.test", agent_type = kind or "callback"}
end
test("Contact maps independently of editable Agent ID", function()
    local id, cacheable = resolve({row()})
    assert(id == agent and cacheable)
end)
test("Non-agent directory is cacheable", function()
    local id, cacheable = resolve({})
    assert(id == nil and cacheable)
end)
test("Foreign domain or custom contact never maps by Agent ID", function()
    assert(resolve({row("user/201@other.test")}) == nil)
    assert(resolve({row("sofia/internal/201")}) == nil)
    assert(#warnings == 2)
end)
test("Ambiguous or standby agents never map arbitrarily", function()
    assert(resolve({row(), row()}) == nil)
    assert(resolve({row(nil, "uuid-standby")}) == nil)
end)
test("Database failures cannot poison the directory cache", function()
    for _, failure in ipairs({"return", "throw"}) do
        local id, cacheable = resolve({}, failure)
        assert(id == nil and not cacheable)
    end
end)
local function call(overrides, module, rejected)
    local vars = {
        fspbx_cc_agent_uuid = agent, fspbx_cc_domain_uuid = domain,
        fspbx_cc_extension_uuid = extension, fspbx_cc_extension = "201",
        fspbx_cc_number_alias = "2001", fspbx_cc_domain_name = "example.test",
        sip_authorized = "true", sip_auth_username = "201",
        domain_uuid = domain, domain_name = "example.test",
        dialed_user = "201", dialed_domain = "example.test",
        execute_on_originate_custom = "set custom_hook=preserved",
        agent_status = "On Break",
    }
    for key, value in pairs(overrides or {}) do vars[key] = value end
    local executions = 0
    local session = {
        ready = function() return false end,
        getState = function() return vars.test_state or "CS_ROUTING" end,
        getVariable = function(_, key) return vars[key] end,
        execute = function(_, application, data)
            assert(application == "callcenter_track" and data == agent)
            executions = executions + 1
            if not rejected then vars.cc_tracked_agent = data end
        end,
    }
    local api = {executeString = function(_, command)
        assert(command == "module_exists mod_callcenter")
        return module == false and "false" or "true\n"
    end}
    return function(mode)
        tracking.track(session, api, mode or "caller", warn)
        assert(vars.execute_on_originate_custom == "set custom_hook=preserved")
        assert(vars.agent_status == "On Break")
        return executions
    end, vars, session, api
end
test("Directory module loading never executes a channel hook", function()
    local _, vars, session = call()
    local environment = setmetatable({session = session, argv = {'caller'}, freeswitch = {
        API = function() error('Directory module loading must not create a call API') end,
    }}, {__index = _G})
    local module = assert(loadfile('resources/freeswitch_scripts/agent_call_track.lua', 't', environment))('agent_call_track')
    assert(type(module.resolve) == 'function' and type(module.track) == 'function')
    assert(vars.cc_tracked_agent == nil)
end)
test("Direct script execution tracks and remains idempotent", function()
    local _, vars, session, api = call()
    local environment = setmetatable({session = session, argv = {'recipient'}, freeswitch = {
        API = function() return api end,
        consoleLog = function() error('Successful calls are silent with debugging disabled') end,
    }}, {__index = _G})
    for i = 1, 2 do
        assert(loadfile('resources/freeswitch_scripts/agent_call_track.lua', 't', environment))()
    end
    assert(vars.cc_tracked_agent == agent)
end)
test("Debugging logs decisions while failure warnings are always enabled", function()
    local file = assert(io.open('resources/freeswitch_scripts/agent_call_track.lua'))
    local source = file:read('*a'); file:close()
    local logs = {}
    local environment = setmetatable({freeswitch = {
        consoleLog = function(level, message) logs[#logs + 1] = level .. ':' .. message end,
    }}, {__index = _G})
    local module = assert(load(source, 'agent_call_track', 't', environment))('agent_call_track')
    local _, _, session, api = call({}, false)
    module.track(session, api, 'caller')
    assert(#logs == 1 and logs[1]:find('WARNING:', 1, true) and logs[1]:find('unavailable', 1, true))
    logs = {}
    source = source:gsub('local DEBUG_MODE = false', 'local DEBUG_MODE = true', 1)
    module = assert(load(source, 'agent_call_track_debug', 't', environment))('agent_call_track')
    _, _, session, api = call()
    module.track(session, api, 'caller')
    module.track(session, api, 'caller')
    local combined = table.concat(logs)
    assert(combined:find('[agent_call_track.lua]', 1, true))
    assert(combined:find('Tracking active:', 1, true) and combined:find('already tracked', 1, true))
end)
test("Authenticated caller and alias track exactly once", function()
    for _, username in ipairs({"201", "2001"}) do
        local run = call({sip_auth_username = username})
        assert(run() == 1 and run() == 1)
    end
end)
test("Recipient uses its own identity before answer", function()
    local run = call({sip_auth_username = "other", domain_uuid = "other"})
    assert(run("recipient") == 1 and run("recipient") == 1)
end)
test("Originate tracks during CS_ROUTING but never after hangup", function()
    assert(call({test_state="CS_ROUTING"})("recipient") == 1)
    for _, state in ipairs({"CS_HANGUP", "CS_REPORTING", "CS_DESTROY", "CS_NONE"}) do
        assert(call({test_state=state})("recipient") == 0)
    end
end)
test("Unauthenticated and foreign callers never track", function()
    for _, values in ipairs({{sip_authorized="false"}, {sip_auth_username="999"}, {domain_uuid="other"}, {domain_name="other"}}) do
        assert(call(values)() == 0)
    end
end)
test("Recipient must match dialed extension and account", function()
    assert(call({dialed_user="999"})("recipient") == 0)
    assert(call({dialed_domain="other.test"})("recipient") == 0)
end)
test("Queue offers, non-agents and malformed identity are excluded", function()
    for _, values in ipairs({{cc_side="agent"}, {fspbx_cc_agent_uuid=""}, {fspbx_cc_agent_uuid="invalid"}, {fspbx_cc_extension_uuid=""}}) do
        assert(call(values)() == 0)
        assert(call(values)("recipient") == 0)
    end
end)
test("Unavailable module and rejected tracking leave calls alone and warn", function()
    assert(call({}, false)() == 0)
    assert(call({}, true, true)() == 1)
    assert(#warnings == 2)
end)
test("Concurrent channels each track independently", function()
    local one, two = call(), call()
    assert(one() == 1 and two() == 1 and one() == 1)
end)
test("Warm directory requests never query the database", function()
    local old_xml, old_cache = package.loaded['resources.functions.xml'], package.loaded['resources.functions.cache']
    local old_tracking = package.loaded['agent_call_track']
    package.loaded['resources.functions.xml'] = {}
    package.loaded['agent_call_track'] = {resolve = function() error('Unexpected membership query on cache hit') end}
    local hits = 0
    package.loaded['resources.functions.cache'] = {
        support = function() return true end,
        get = function(key)
            assert(key == 'directory:201@example.test')
            hits = hits + 1
            return '<document>cached-directory-' .. hits .. '</document>'
        end,
    }
    local environment = setmetatable({
        debug = {}, xml_handler = {}, user = '201', domain_name = 'example.test',
        params = {getHeader = function(_, key) if key == 'action' then return 'sip_auth' end end},
        freeswitch = {consoleLog = function() end},
        Database = {new = function() error('Unexpected database connection on cache hit') end},
    }, {__index = _G})
    for i = 1, 2 do
        assert(loadfile('resources/freeswitch_scripts/app/xml_handler/resources/scripts/directory/directory.lua', 't', environment))()
        assert(environment.XML_STRING == '<document>cached-directory-' .. i .. '</document>')
    end
    assert(hits == 2)
    package.loaded['resources.functions.xml'], package.loaded['resources.functions.cache'] = old_xml, old_cache
    package.loaded['agent_call_track'] = old_tracking
end)
print(tests .. " Lua tracking tests passed")
