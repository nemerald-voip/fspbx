package.path = 'resources/freeswitch_scripts/?.lua;' .. package.path
local Xml = require 'resources.functions.xml'
local directory = 'resources/freeswitch_scripts/app/xml_handler/resources/scripts/directory/directory.lua'
local domain = 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb'
local extension = 'cccccccc-cccc-4ccc-cccc-cccccccccccc'
local agent = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa'
local documents, tests = {}, 0
local function test(name, run)
    run()
    tests = tests + 1
    print('PASS ' .. name)
end
local function contains(text, fragment) assert(text:find(fragment, 1, true), fragment) end
local function absent(text, fragment) assert(not text:find(fragment, 1, true), fragment) end
local function build(options, environment)
    local o = options or {}
    local stats = {queries = {}, releases = {}, created = {}, logs = {}, cache = {}, settings = 0}
    local replaced = {}
    local function replace(name, value)
        replaced[name] = package.loaded[name]
        package.loaded[name] = value
    end
    local row = setmetatable({
        domain_uuid = domain, extension_uuid = extension, extension = '201', number_alias = '2001',
        password = 'fixture-value', user_context = 'example.test',
    }, {__index = function() return '' end})
    for k, v in pairs(o.row or {}) do row[k] = v end
    replace('resources.functions.database', {new = function(name)
        stats.created[name] = (stats.created[name] or 0) + 1
        return {
            connected = function() return not o.disconnected end,
            release = function() stats.releases[name] = (stats.releases[name] or 0) + 1 end,
            first_row = function(_, sql, params)
                DIRECTORY_OWNER_SQL = sql
                stats.queries[#stats.queries + 1] = sql
                contains(sql, 'LIMIT 1) AS eu')
                contains(sql, 'LEFT JOIN v_users AS u ON u.user_uuid = eu.user_uuid AND u.domain_uuid = :domain_uuid')
                assert(params.domain_uuid == domain and params.extension_uuid == extension)
                return o.owner
            end,
            query = function(_, sql, params, callback)
                stats.queries[#stats.queries + 1] = sql
                if o.fail and sql:find(o.fail, 1, true) then error('fixture query failure') end
                if sql:find('FROM registrations', 1, true) then
                    callback({hostname = 'node-b'})
                elseif sql:find('SELECT domain_uuid FROM v_domains', 1, true) then
                    if not o.missing_domain then callback({domain_uuid = domain}) end
                elseif sql:find('SELECT e.*', 1, true) then
                    assert(params.domain_uuid == domain)
                    if not o.missing_extension then callback(row) end
                elseif sql:find('FROM v_extension_settings', 1, true) then
                    for _, setting in ipairs(o.extension_settings or {}) do callback(setting) end
                elseif sql:find('FROM v_voicemails', 1, true) then
                    assert(params.domain_uuid == domain)
                    stats.voicemail_id = params.voicemail_id
                    if o.voicemail then callback(setmetatable(o.voicemail, {__index = function() return '' end})) end
                elseif sql:find('FROM v_call_center_agents', 1, true) then
                    assert(params.domain_uuid == domain)
                    if o.membership_failure then return nil, 'fixture membership failure' end
                    if o.agent then callback({call_center_agent_uuid = agent, agent_type = 'callback', agent_contact = 'user/201@example.test'}) end
                else
                    error('Unexpected fixture query')
                end
                return true
            end,
        }
    end})
    replace('resources.functions.lazy_settings', {new = function()
        stats.settings = stats.settings + 1
        return {get = function() return o.dial_string end}
    end})
    replace('resources.functions.cache', {
        support = function() return true end,
        get = function(key) stats.cache_key = key; return o.cached end,
        set = function(key, value) stats.cache[key] = value; return true end,
    })
    local env = environment or setmetatable({}, {__index = _G})
    env.user, env.domain_name, env.domain_uuid = '201', 'example.test', nil
    env.debug, env.expire, env.database, env.xml_handler = {}, {}, {type = 'pgsql'}, o.flags or {}
    env.params = {getHeader = function(_, key)
        if key == 'action' then return o.action or 'sip_auth' end
        if key == 'dialed_extension' and o.flags and o.flags.fs_path == 'true' then return '201' end
        return (o.headers or {})[key]
    end}
    env.freeswitch = {consoleLog = function(_, message) stats.logs[#stats.logs + 1] = message end}
    env.api = {execute = function(_, command)
        if command == 'switchname' then return 'node-a' end
        if command == 'user_data' then return '201' end
        if command == 'sofia_contact' then return 'sofia/internal/201@example.test' end
        error('Unexpected fixture API')
    end}
    env.trim = function(s) return s end
    env.explode = function() return {'sofia', 'internal', '201@example.test'} end
    env.scripts_dir = 'resources/freeswitch_scripts'
    env.dofile = function(path)
        stats.delegated = path
        env.XML_STRING = '<document type="freeswitch/xml"/>'
    end
    local ok, err = pcall(assert(loadfile(directory, 't', env)))
    for name in pairs(replaced) do package.loaded[name] = replaced[name] end
    -- Restore modules that were not previously loaded too.
    for _, name in ipairs({'resources.functions.database', 'resources.functions.lazy_settings', 'resources.functions.cache'}) do
        package.loaded[name] = replaced[name]
    end
    assert(ok, err)
    documents[#documents + 1] = env.XML_STRING:gsub('<%?xml.-%?>', '')
    return env.XML_STRING, stats, env
end

test('XML escaping preserves raw passwords and dial expressions', function()
    local password = [[fixture$&"<>'value]]
    local dial = [[{label="A&B"}${sofia_contact(*/201@example.test)}]]
    local xml, stats = build({row = {password = password, effective_caller_id_name = 'Sales & Support'}, dial_string = dial})
    contains(xml, 'name="password" value="fixture$&amp;&quot;&lt;&gt;&apos;value"')
    contains(xml, 'name="dial-string" value="{label=&quot;A&amp;B&quot;}${sofia_contact(*/201@example.test)}"')
    contains(xml, 'name="effective_caller_id_name" value="Sales &amp; Support"')
    assert(Xml.sanitize('${legacy}&') == '{legacy}&amp;')
    assert(stats.releases.system == 1)
end)
test('Disabled alias flags keep extension dialing and presence', function()
    for _, value in ipairs({false, 'false'}) do
        local xml = build({flags = {reg_as_number_alias = value, number_as_presence_id = value}})
        contains(xml, '${sofia_contact(*/201@example.test)}')
        contains(xml, 'name="presence_id" value="201@example.test"')
    end
end)
test('Enabled alias flags retain alias dialing and both cache entries', function()
    for _, value in ipairs({true, 'true'}) do
        local xml, stats = build({flags = {reg_as_number_alias = value, number_as_presence_id = value}})
        contains(xml, '${sofia_contact(*/2001@example.test)}')
        contains(xml, 'name="presence_id" value="2001@example.test"')
        assert(stats.cache['directory:201@example.test'] == xml and stats.cache['directory:2001@example.test'] == xml)
        assert(stats.voicemail_id == '2001')
    end
end)
test('Unknown extensions and domains release handles without caching', function()
    for _, options in ipairs({{missing_extension = true}, {missing_domain = true}, {disconnected = true}}) do
        local xml, stats = build(options)
        contains(xml, 'status="not found"')
        assert(stats.releases.system == 1 and next(stats.cache) == nil)
        if options.missing_domain then assert(stats.settings == 0) end
    end
end)
test('Exceptions release system and switch database handles', function()
    local xml, stats = build({fail = 'FROM v_extension_settings'})
    contains(xml, 'status="not found"')
    assert(stats.releases.system == 1 and #stats.logs > 0 and next(stats.cache) == nil)
    xml, stats = build({flags = {fs_path = 'true'}, fail = 'FROM registrations'})
    contains(xml, 'status="not found"')
    assert(stats.releases.system == 1 and stats.releases.switch == 1 and next(stats.cache) == nil)
end)
test('Load-balancing dial strings still route to the registered server', function()
    local xml, stats = build({flags = {fs_path = 'true'}})
    contains(xml, 'sofia/internal/201@example.test;fs_path=sip:node-b')
    assert(stats.releases.system == 1 and stats.releases.switch == 1)
end)
test('One tenant-scoped owner query retains user and optional contact', function()
    for _, owner in ipairs({{user_uuid = 'owner', contact_uuid = 'contact'}, {user_uuid = 'orphan'}}) do
        local xml, stats = build({owner = owner})
        contains(xml, 'name="user_uuid" value="' .. owner.user_uuid .. '"')
        if owner.contact_uuid then contains(xml, 'name="contact_uuid" value="contact"')
        else absent(xml, 'name="contact_uuid"') end
        local count = 0
        for _, sql in ipairs(stats.queries) do if sql:find('v_extension_users', 1, true) then count = count + 1 end end
        assert(count == 1)
    end
end)
test('Repeated lookups cannot inherit optional owner or follow-me values', function()
    local _, _, env = build({owner = {user_uuid = 'owner', contact_uuid = 'contact'}, row = {follow_me_uuid = 'follow', follow_me_enabled = 'true'}})
    local xml = build({}, env)
    absent(xml, 'name="user_uuid"'); absent(xml, 'name="contact_uuid"'); absent(xml, 'name="follow_me_enabled"')
end)
test('Voicemail, custom settings and originate hooks are preserved', function()
    local xml, stats = build({agent = true, voicemail = {voicemail_password = '4321', voicemail_mail_to = 'fixture@example.test'}, extension_settings = {
        {extension_setting_type = 'param', extension_setting_name = 'dial-var-execute_on_originate_custom', extension_setting_value = 'set label=A&B'},
        {extension_setting_type = 'variable', extension_setting_name = 'custom_value', extension_setting_value = 'A&B'},
    }})
    contains(xml, 'name="vm-password" value="4321"')
    contains(xml, 'name="vm-mailto" value="fixture@example.test"')
    contains(xml, 'name="dial-var-execute_on_originate_custom" value="set label=A&amp;B"')
    contains(xml, 'name="custom_value" value="A&amp;B"')
    contains(xml, 'name="dial-var-execute_on_originate_fspbx_cc" value="lua agent_call_track.lua recipient"')
    assert(stats.releases.system == 1)
end)
test('Do-not-disturb and custom extension dial strings retain precedence', function()
    local xml = build({row = {do_not_disturb = 'true', dial_string = 'custom'}})
    contains(xml, 'name="dial-string" value="error/user_busy"')
    xml = build({dial_string = 'domain-default', row = {dial_string = '${custom_contact}'}})
    contains(xml, 'name="dial-string" value="${custom_contact}"')
end)
test('Membership failure still returns a directory without caching partial identity', function()
    local xml, stats = build({membership_failure = true})
    contains(xml, '<section name="directory">')
    absent(xml, 'fspbx_cc_agent_uuid')
    assert(stats.releases.system == 1 and next(stats.cache) == nil)
end)
test('Warm directory response performs no database or settings work', function()
    local xml, stats = build({cached = '<document type="freeswitch/xml"/>'})
    assert(xml == '<document type="freeswitch/xml"/>' and next(stats.created) == nil and stats.settings == 0)
end)
test('Special directory actions continue delegating to their existing scripts', function()
    local scenarios = {
        {{headers = {purpose = 'gateways'}}, 'domains.lua'},
        {{action = 'message-count'}, 'message-count.lua'},
        {{action = 'group_call'}, 'group_call.lua'},
        {{action = 'reverse-auth-lookup'}, 'reverse-auth-lookup.lua'},
        {{headers = {['Event-Calling-Function'] = 'switch_xml_locate_domain'}}, 'domains.lua'},
        {{headers = {['Event-Calling-Function'] = 'switch_load_network_lists'}}, 'acl.lua'},
        {{headers = {['Event-Calling-Function'] = 'populate_database', ['Event-Calling-File'] = 'mod_directory.c'}}, 'directory.lua'},
    }
    for _, scenario in ipairs(scenarios) do
        local _, stats = build(scenario[1])
        assert(stats.delegated == 'resources/freeswitch_scripts/app/xml_handler/resources/scripts/directory/action/' .. scenario[2])
        assert(next(stats.created) == nil)
    end
end)
DIRECTORY_XML_FIXTURES = '<fixtures>' .. table.concat(documents) .. '</fixtures>'
print(tests .. ' directory tests passed')
