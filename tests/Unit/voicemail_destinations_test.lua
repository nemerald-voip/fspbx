-- Run from the repository root with Lua 5.2+. No live calls, files, or database access.
local script = 'resources/freeswitch_scripts/app/voicemail/index.lua'
local tests = 0

local function check(name, targets, missing_original)
    local messages, copies, directories, notifications, emails, webhooks, warnings = {}, {}, {}, {}, {}, {}, {}
    local mailboxes = {
        original = {domain_uuid = 'account-a', voicemail_id = '100', voicemail_mail_to = 'original@example.test'},
        first = {domain_uuid = 'account-a', voicemail_id = '101', voicemail_mail_to = 'first@example.test'},
        last = {domain_uuid = 'account-a', voicemail_id = '102', voicemail_mail_to = ''},
        foreign = {domain_uuid = 'account-b', voicemail_id = '101', voicemail_mail_to = 'foreign@example.test'},
    }
    if missing_original then mailboxes.original = nil end
    local sequence, released = 0, false
    local env = setmetatable({
        argv = {'voicemail', 'save'}, debug = {},
        domain_uuid = 'account-a', domain_name = 'example.test',
        voicemail_uuid = 'original', voicemail_id = '100', uuid = 'original-message',
        voicemail_dir = '/voicemail/example.test', vm_message_ext = 'wav', storage_type = '',
        message_length = '10', start_epoch = 1700000000,
        caller_id_name = 'Caller', caller_id_number = '123', settings = {},
        play_greeting = function() end, record_message = function() end,
        format_seconds = function() return '00:00:10' end,
    }, {__index = _G})
    local db = {}
    function db:query(sql, params, callback)
        if sql:find('from v_voicemail_destinations', 1, true) then
            assert(sql:find('domain_uuid = :domain_uuid', 1, true), 'Copy list must be account scoped')
            assert(params.domain_uuid == 'account-a' and params.voicemail_uuid == 'original')
            for _, target in ipairs(targets) do callback({voicemail_uuid_copy = target}) end
        elseif sql:find('FROM v_voicemails', 1, true) then
            assert(sql:find('domain_uuid = :domain_uuid', 1, true), 'Mailbox lookup must be account scoped')
            assert(params.domain_uuid == 'account-a')
            local mailbox = mailboxes[params.voicemail_uuid]
            if mailbox and mailbox.domain_uuid == params.domain_uuid then callback(mailbox) end
        elseif sql:find('INSERT INTO v_voicemail_messages', 1, true) then
            local mailbox = mailboxes[params.voicemail_uuid]
            assert(mailbox and mailbox.domain_uuid == params.domain_uuid, 'Orphan or foreign message inserted')
            messages[#messages + 1] = params
        elseif sql:find('count(*) as new_messages', 1, true) then
            callback({new_messages = '1'})
        elseif sql:find('count(*) as saved_messages', 1, true) then
            callback({saved_messages = '0'})
        elseif sql:find('sms_notifications_enabled', 1, true) then
            callback({value = 'true'})
        else
            error('Unexpected SQL: ' .. sql)
        end
    end
    function db:release() released = true end
    env.require = function(name)
        if name == 'resources.functions.database' then return {new = function() return db end} end
        return {}
    end
    env.freeswitch = {
        consoleLog = function(level, message)
            if level == 'warning' then warnings[#warnings + 1] = message end
        end,
        API = function() return {
            execute = function(_, command)
                assert(command == 'create_uuid')
                sequence = sequence + 1
                return 'copy-' .. sequence
            end,
            executeString = function(_, command) webhooks[#webhooks + 1] = command end,
        } end,
    }
    env.mkdir = function(path) directories[#directories + 1] = path end
    env.file_exists = function(path)
        assert(path == '/voicemail/example.test/100/msg_original-message.wav')
        return true
    end
    env.copy = function(src, dst) copies[#copies + 1] = dst end
    env.message_waiting = function(id, domain)
        assert(domain == 'account-a')
        notifications[#notifications + 1] = id
    end
    env.send_email = function(id, uuid) emails[#emails + 1] = {id = id, uuid = uuid} end

    assert(loadfile(script, 't', env))()
    assert(released, 'Database connection was not released')
    local expected, expected_warnings = {}, 0
    for _, target in ipairs(targets) do
        if mailboxes[target] and mailboxes[target].domain_uuid == 'account-a' then
            expected[#expected + 1] = target
        else
            expected_warnings = expected_warnings + 1
            assert(warnings[expected_warnings]:find(target, 1, true), 'Warning must identify invalid target')
            assert(warnings[expected_warnings]:find('account-a', 1, true))
        end
    end
    local expected_copies = #expected
    if missing_original then expected_warnings = expected_warnings + 1
    else expected[#expected + 1] = 'original' end
    assert(#warnings == expected_warnings, name .. ': unexpected warning count')
    assert(#messages == #expected and #notifications == #expected and #directories == #expected)
    assert(#copies == expected_copies, name .. ': invalid or original mailbox copied')
    assert(#webhooks == #expected * 2, name .. ': unexpected created/SMS webhooks')
    local email_index = 0
    for index, target in ipairs(expected) do
        local mailbox, message = mailboxes[target], messages[index]
        assert(message.voicemail_uuid == target and message.domain_uuid == 'account-a')
        assert(notifications[index] == mailbox.voicemail_id)
        assert(directories[index] == '/voicemail/example.test/' .. mailbox.voicemail_id)
        assert(webhooks[index * 2 - 1]:find('"voicemail_uuid":"' .. target .. '"', 1, true))
        assert(webhooks[index * 2]:find('"voicemail_id":"' .. mailbox.voicemail_id .. '"', 1, true))
        if target == 'original' then
            assert(message.voicemail_message_uuid == 'original-message', 'Original recording UUID changed')
        else
            assert(copies[index] == '/voicemail/example.test/' .. mailbox.voicemail_id
                .. '/msg_' .. message.voicemail_message_uuid .. '.wav')
        end
        if mailbox.voicemail_mail_to ~= '' then
            email_index = email_index + 1
            assert(emails[email_index].id == mailbox.voicemail_id)
            assert(emails[email_index].uuid == message.voicemail_message_uuid)
        end
    end
    assert(#emails == email_index, name .. ': stale recipient reused')
    tests = tests + 1
    print('PASS ' .. name)
end

check('original mailbox only', {})
check('valid destinations including a mailbox without email', {'first', 'last'})
check('missing destination between valid destinations', {'first', 'missing', 'last'})
check('missing first destination', {'missing', 'first'})
check('missing last destination preserves original UUID', {'first', 'missing'})
check('all copy destinations missing', {'missing', 'also-missing'})
check('destination in another account', {'first', 'foreign', 'last'})
check('original mailbox removed while recording', {'first', 'missing', 'last'}, true)
print(tests .. ' voicemail destination regression tests passed')
