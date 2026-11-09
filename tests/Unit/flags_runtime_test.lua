-- Run from the repository root with Lua 5.2+. No live FreeSWITCH or database calls.
local root = 'resources/freeswitch_scripts/'
package.path = root .. '?.lua;' .. package.path
package.loaded['resources.functions.config'] = true
local settings, flags, rules, events, handles, probes
local queries, updates, consumers, clock, sleeps, allowed, allowed_count, pin
local fail_settings, fail_rules, fail_lookup, now, stop_on_sleep

local function reset()
    settings = {{ default_setting_enabled = 'true', default_setting_value = 'true' }}
    flags = {
        a = { uuid = 'a', domain_uuid = 'tenant', flag_number = '1', type = 'manual', active = false, enabled = true, blf_enabled = true },
        b = { uuid = 'b', domain_uuid = 'tenant', flag_number = '2', type = 'manual', active = true, enabled = true, blf_enabled = true },
    }
    rules, events, handles, probes = {}, {}, {}, {}
    queries, updates, consumers, clock, sleeps = 0, 0, 0, 1000, 0
    allowed, allowed_count, pin = true, 0, '1234'
    fail_settings, fail_rules, fail_lookup = false, false, false
    now, stop_on_sleep = '2026-11-09 10:00 1', true
end

package.loaded['resources.functions.database'] = { new = function()
    local db = { released = false }
    handles[#handles + 1] = db
    function db:connected() return true end
    function db:release() self.released = true end
    function db:query(sql, params, callback)
        if sql:find('FROM v_default_settings', 1, true) then
            if fail_settings then error('settings unavailable') end
            for _, row in ipairs(settings) do callback(row) end
            return true
        end
        queries = queries + 1
        if sql:find('FROM flag_rules', 1, true) then
            if fail_rules then return nil, 'rules unavailable' end
            for _, row in ipairs(rules[params.flag_uuid] or {}) do callback(row) end
        elseif sql:find('FROM flags', 1, true) then
            for _, row in pairs(flags) do
                if row.type == 'scheduled' and row.blf_enabled then callback(row) end
            end
        else error('Unexpected query: ' .. sql) end
        return true
    end
    function db:first_row(sql, params)
        queries = queries + 1
        if fail_lookup then return nil, 'lookup failed' end
        if sql:find('UPDATE flags', 1, true) then
            assert(sql:find('active = NOT active', 1, true), 'Toggle must be atomic')
            local flag = flags[params.uuid]
            assert(flag.domain_uuid == params.domain_uuid)
            flag.active = not flag.active
            updates = updates + 1
            return { active = flag.active }
        end
        for _, row in pairs(flags) do
            if (params.flag_uuid == row.uuid or params.flag_number == row.flag_number)
                and (params.domain_uuid == row.domain_uuid or (params.domain_name and params.domain_name == row.domain_name)) then
                return row
            end
        end
    end
    function db:first_value(sql, params)
        queries = queries + 1
        if sql:find('FROM v_extensions', 1, true) then
            return params.domain_uuid == 'tenant' and params.extension_uuid == 'extension' and 1 or nil
        end
        if sql:find('count(*)', 1, true) then return allowed_count end
        return allowed and 1 or nil
    end
    return db
end }

freeswitch = {
    consoleLog = function() end,
    API = function() return { execute = function(_, command)
        if command == 'strftime_tz' or command == 'strftime' then return now end
        return 'test-uuid'
    end } end,
    Event = function(name)
        local event = { name = name, headers = {} }
        function event:addHeader(key, value) self.headers[key] = value end
        function event:fire() events[#events + 1] = self end
        return event
    end,
    EventConsumer = function()
        consumers = consumers + 1
        return { pop = function() return table.remove(probes, 1) end }
    end,
    msleep = function()
        sleeps = sleeps + 1
        assert(sleeps < 5, 'Daemon did not exit after disable')
        clock = clock + 1
        if stop_on_sleep then settings[1].default_setting_value = 'false' end
    end,
}
os.time = function() return clock end

local tests = 0
local function check(name, callback)
    reset()
    callback()
    for _, handle in ipairs(handles) do assert(handle.released, name .. ': database handle leaked') end
    tests = tests + 1
    print('PASS ' .. name)
end

local function match(args)
    argv = args
    local value
    stream = { write = function(_, result) value = result end }
    dofile(root .. 'lua/flag_check.lua')
    return value
end

for _, variant in ipairs({'off', 'missing', 'disabled row', 'ambiguous', 'unreadable'}) do
    check('stale active and inactive XML bypasses Flags: ' .. variant, function()
        if variant == 'off' then settings[1].default_setting_value = 'false'
        elseif variant == 'missing' then settings = {}
        elseif variant == 'disabled row' then settings[1].default_setting_enabled = 'false'
        elseif variant == 'ambiguous' then settings[2] = settings[1]
        else fail_settings = true end
        assert(match({'tenant', 'a:inactive', 'b'}) == '0')
        assert(queries == 0, 'Disabled path queried Flags tables')
    end)
end
check('first matching flag wins', function() assert(match({'tenant', 'a', 'b'}) == '2') end)
check('inactive match wins in row order', function() assert(match({'tenant', 'a:inactive', 'b'}) == '1') end)
check('disabled flags never match either state', function()
    flags.a.enabled = false
    assert(match({'tenant', 'a', 'a:inactive', 'b'}) == '3')
end)
check('foreign account flags never match', function() assert(match({'other', 'b'}) == '0') end)
check('lookup errors keep normal routing', function()
    fail_lookup = true
    assert(match({'tenant', 'a:inactive', 'b'}) == '0')
end)
check('schedule lookup errors do not activate inactive overrides', function()
    flags.a.type, fail_rules = 'scheduled', true
    assert(match({'tenant', 'a:inactive', 'b'}) == '0')
end)
check('clock errors do not activate inactive overrides', function()
    flags.a.type, now = 'scheduled', '-ERR invalid timezone'
    assert(match({'tenant', 'a:inactive', 'b'}) == '0')
end)
check('weekly schedule uses the configured interval', function()
    flags.a.type, flags.a.timezone = 'scheduled', 'America/Toronto'
    rules.a = {{ rule_type = 'weekly', weekdays = '2', start_time = '09:00', end_time = '17:00' }}
    assert(match({'tenant', 'a'}) == '1')
    now = '2026-11-09 17:00 1'
    assert(match({'tenant', 'a'}) == '0')
end)
check('overnight weekly and date range rules', function()
    flags.a.type = 'scheduled'
    rules.a = {{ rule_type = 'weekly', weekdays = '2', start_time = '22:00', end_time = '06:00' }}
    now = '2026-11-10 01:00 2'
    assert(match({'tenant', 'a'}) == '1')
    rules.a = {{ rule_type = 'date_range', start_date = '2026-11-09', end_date = '2026-11-10' }}
    assert(match({'tenant', 'a'}) == '1')
    now = '2026-11-11 01:00 3'
    assert(match({'tenant', 'a'}) == '0')
end)

local function toggle(extension)
    session = {
        ready = function() return true end, answer = function() end, sleep = function() end,
        streamFile = function() end, hangup = function() end,
        playAndGetDigits = function() return pin end,
        getVariable = function(_, key) return ({domain_uuid = 'tenant', domain_name = 'tenant.test', destination_number = 'flag1', extension_uuid = extension})[key] end,
    }
    dofile(root .. 'lua/flag_toggle.lua')
end
check('phone cannot toggle while server switch is off', function()
    settings[1].default_setting_value = 'false'; toggle('extension')
    assert(updates == 0 and queries == 0)
end)
check('non-extension caller cannot toggle', function() toggle('foreign'); assert(updates == 0) end)
check('allowed extension list is enforced', function()
    allowed_count, allowed = 1, false; toggle('extension'); assert(updates == 0)
end)
check('unreadable allowed extension list denies the toggle', function()
    allowed_count = nil; toggle('extension'); assert(updates == 0)
end)
check('PIN is enforced', function()
    flags.a.pin_number, pin = '1234', '0000'; toggle('extension'); assert(updates == 0)
end)
check('scheduled flags cannot be toggled', function()
    flags.a.type = 'scheduled'; toggle('extension'); assert(updates == 0)
end)
check('manual toggles alternate atomically', function()
    flags.a.pin_number = '1234'; toggle('extension'); assert(flags.a.active and updates == 1)
    toggle('extension'); assert(not flags.a.active and updates == 2)
end)
check('disabled daemon creates no event consumer', function()
    settings[1].default_setting_value = 'false'
    dofile(root .. 'lua/flag_blf.lua'); assert(consumers == 0 and queries == 0)
end)
check('running daemon exits after switch is disabled', function()
    dofile(root .. 'lua/flag_blf.lua'); assert(consumers == 1 and sleeps == 1)
end)
check('manual presence notification is guarded', function()
    argv = {'1', 'tenant.test', 'true'}
    settings[1].default_setting_value = 'false'
    dofile(root .. 'lua/flag_notify.lua'); assert(#events == 0)
    settings[1].default_setting_value = 'true'
    dofile(root .. 'lua/flag_notify.lua'); assert(#events == 2)
end)
print(tests .. ' Flags runtime tests passed')
