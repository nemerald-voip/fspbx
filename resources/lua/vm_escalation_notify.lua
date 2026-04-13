json = require "resources.functions.lunajson"

-- Enable/Disable debug mode globally
DEBUG_MODE = false -- Set to true to enable debug logs

local SCRIPT_NAME = "[vm_escalation_notify.lua]"
local SOUND_PREFIX = "/var/www/fspbx/resources/sounds/en/us/alloy/vm_escalation_notify"

api = freeswitch.API()

-- Debug logging function
local function debug_log(level, message)
    if DEBUG_MODE then
        freeswitch.consoleLog(level, SCRIPT_NAME .. " " .. tostring(message) .. "\n")
    end
end

local function safe(value)
    if value == nil then return "" end
    return tostring(value)
end

local function shell_quote(str)
    str = tostring(str or "")
    return "'" .. str:gsub("'", "'\\''") .. "'"
end

local function file_exists(path)
    local f = io.open(path, "rb")
    if f then
        f:close()
        return true
    end
    return false
end

local function get_prompt_digit(prompt_file, timeout_ms)
    local fullpath = SOUND_PREFIX .. "/" .. prompt_file

    if not file_exists(fullpath) then
        debug_log("ERR", "Missing interruptible prompt file: " .. fullpath)
        return ""
    end

    debug_log("INFO", "Playing interruptible prompt: " .. fullpath)

    local digit = session:playAndGetDigits(
        1,                  -- min digits
        1,                  -- max digits
        1,                  -- tries
        timeout_ms or 7000, -- timeout
        "#",                -- terminator
        prompt_file,        -- prompt file
        "",                 -- invalid file
        "\\d"               -- digit regex
    )

    debug_log("INFO", "Received DTMF during prompt: " .. safe(digit))
    return digit or ""
end

local function play_prompt(filename)
    local fullpath = SOUND_PREFIX .. "/" .. filename

    if not file_exists(fullpath) then
        debug_log("ERR", "Missing prompt file: " .. fullpath)
        return false
    end

    debug_log("INFO", "Playing prompt: " .. fullpath)
    session:streamFile(filename)
    return true
end

local function say_number(number)
    number = safe(number)

    if number ~= "" then
        debug_log("INFO", "Saying number: " .. number)
        session:execute("say", "en number iterated " .. number)
    end
end

local function send_attempt_event(action, extra)
    local payload = {
        event = "vm_notify_attempt_event",
        timestamp = os.date("!%Y-%m-%dT%H:%M:%SZ"),
        data = {
            action = action,
            vm_notify_attempt_uuid = session:getVariable("vm_notify_attempt_uuid"),
            vm_notify_notification_uuid = session:getVariable("vm_notify_notification_uuid"),
            vm_notify_profile_uuid = session:getVariable("vm_notify_profile_uuid"),
            vm_notify_voicemail_uuid = session:getVariable("vm_notify_voicemail_uuid"),
            vm_notify_voicemail_message_uuid = session:getVariable("vm_notify_voicemail_message_uuid"),
            vm_notify_domain_uuid = session:getVariable("vm_notify_domain_uuid"),
            vm_notify_domain_name = session:getVariable("vm_notify_domain_name"),
            vm_notify_mailbox = session:getVariable("vm_notify_mailbox"),
            vm_notify_caller_id_name = session:getVariable("vm_notify_caller_id_name"),
            vm_notify_caller_id_number = session:getVariable("vm_notify_caller_id_number"),
            dtmf_sequence = safe(session:getVariable("vm_notify_dtmf_sequence")),
            reason = extra and extra.reason or nil,
        }
    }

    local encoded = json.encode(payload)
    local cmd = string.format("luarun lua/send_webhook.lua '%s'", encoded)

    debug_log("INFO", "Sending attempt event: " .. action)
    freeswitch.API():executeString(cmd)
end

local function claim_message()
    local attempt_uuid = session:getVariable("vm_notify_attempt_uuid")
    local notification_uuid = session:getVariable("vm_notify_notification_uuid")
    local dtmf_sequence = session:getVariable("vm_notify_dtmf_sequence") or ""

    local cmd = string.format(
        "/usr/bin/php /var/www/fspbx/artisan vm-notify:claim %s %s --dtmf=%s 2>/dev/null",
        shell_quote(attempt_uuid),
        shell_quote(notification_uuid),
        shell_quote(dtmf_sequence)
    )

    debug_log("INFO", "Claim command: " .. cmd)

    local handle = io.popen(cmd)
    if not handle then
        debug_log("ERR", "Failed to open claim command pipe")
        return { ok = false, reason = "claim_command_failed" }
    end

    local result = handle:read("*a") or ""
    handle:close()

    result = result:gsub("^%s+", ""):gsub("%s+$", "")
    debug_log("INFO", "Claim result: " .. safe(result))

    if result == "accepted" then
        return { ok = true, reason = "accepted" }
    elseif result == "already_claimed" then
        return { ok = false, reason = "already_claimed" }
    elseif result == "attempt_not_found" then
        return { ok = false, reason = "attempt_not_found" }
    elseif result == "notification_not_found" then
        return { ok = false, reason = "notification_not_found" }
    elseif result == "mismatch" then
        return { ok = false, reason = "mismatch" }
    else
        return { ok = false, reason = "error" }
    end
end

local function get_digit(timeout_ms)
    local digit = session:playAndGetDigits(1, 1, 1, timeout_ms or 5000, "#", "silence_stream://200", "", "\\d")
    debug_log("INFO", "Received DTMF: " .. safe(digit))
    return digit
end

local function add_dtmf(d)
    if d and #d > 0 then
        local existing = safe(session:getVariable("vm_notify_dtmf_sequence"))
        local updated = existing .. d
        session:setVariable("vm_notify_dtmf_sequence", updated)
        debug_log("INFO", "Updated DTMF sequence: " .. updated)
    end
end

local function say_main_menu()
    local mailbox = safe(session:getVariable("vm_notify_mailbox"))

    play_prompt("you_have_new_voicemail_in_mailbox.wav")
    say_number(mailbox)
    session:sleep(250)

    return get_prompt_digit("main_menu_options.wav", 7000)
end

local function say_post_playback_menu()
    return get_prompt_digit("post_playback_menu.wav", 7000)
end

local function say_caller_id()
    local cid_name = safe(session:getVariable("vm_notify_caller_id_name"))
    local cid_number = safe(session:getVariable("vm_notify_caller_id_number"))

    if cid_name ~= "" then
        debug_log("INFO", "Caller ID name available but not spoken without TTS: " .. cid_name)
        play_prompt("caller_id_name_is_not_available.wav")
    end

    if cid_number ~= "" then
        play_prompt("caller_id_number_is.wav")
        session:sleep(150)
        say_number(cid_number)
    else
        play_prompt("caller_id_number_is_not_available.wav")
    end
end

local function play_message()
    local path = session:getVariable("vm_notify_message_path")

    debug_log("INFO", "vm_notify_message_path = " .. safe(session:getVariable("vm_notify_message_path")))

    if path and #path > 0 then
        debug_log("INFO", "Playing voicemail message: " .. path)
        send_attempt_event("playback_started", nil)
        session:streamFile(path)
        send_attempt_event("playback_completed", nil)
        return true
    end

    debug_log("ERR", "Voicemail recording path missing")
    play_prompt("the_voicemail_recording_could_not_be_found.wav")
    send_attempt_event("failed", { reason = "message_not_found" })
    return false
end

debug_log("INFO", "Executing voicemail notify Lua script")

if (session == nil or not session:ready()) then
    debug_log("ERR", "Session is nil or not ready")
    return
end

session:setVariable("sound_prefix", SOUND_PREFIX)
debug_log("INFO", "Set sound_prefix to: " .. SOUND_PREFIX)

session:answer()
session:sleep(500)
send_attempt_event("answered", nil)

local finished = false
local loops = 0

while session:ready() and not finished and loops < 5 do
    loops = loops + 1
    debug_log("INFO", "Menu loop #" .. tostring(loops))

    local digit = say_main_menu()
    add_dtmf(digit)

    if digit == "1" then
        local played = play_message()

        if played and session:ready() then
            session:sleep(250)
            local post_digit = say_post_playback_menu()
            add_dtmf(post_digit)

            if post_digit == "1" then
                local claim = claim_message()

                if claim.ok == true then
                    play_prompt("you_have_accepted_responsibility_for_this_message.wav")
                    send_attempt_event("accepted", nil)
                else
                    if claim.reason == "already_claimed" then
                        play_prompt("the_message_has_already_been_accepted_by_another_person.wav")
                    else
                        play_prompt("we_were_unable_to_accept_your_request_at_this_time.wav")
                    end

                    send_attempt_event("claim_failed", { reason = claim.reason })
                end

                finished = true

            elseif post_digit == "2" then
                send_attempt_event("declined", nil)
                play_prompt("you_have_declined_responsibility_for_this_message.wav")
                finished = true

            else
                play_prompt("no_valid_selection_was_received.wav")
            end
        else
            finished = true
        end

    elseif digit == "2" then
        send_attempt_event("declined", nil)
        play_prompt("you_have_declined_responsibility_for_this_message.wav")
        finished = true

    elseif digit == "3" then
        send_attempt_event("caller_id_requested", nil)
        say_caller_id()

    else
        play_prompt("no_valid_selection_was_received.wav")
    end
end

send_attempt_event("hungup", nil)

if session:ready() then
    debug_log("INFO", "Hanging up call")
    session:hangup("NORMAL_CLEARING")
end