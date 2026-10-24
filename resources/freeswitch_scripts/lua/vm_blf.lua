-- vm_blf.lua
-- Voicemail BLF presence daemon for FreeSWITCH
--
-- Behavior:
--   Phone SUBSCRIBEs to:  vm200@domain
--   -> FreeSWITCH generates PRESENCE_PROBE
--   -> This script looks up mailbox 200 on that domain
--   -> If there are new messages  => BLF LED ON
--      If not => BLF LED OFF
--
-- BLF AoR format: vm<mailbox>@domain  (e.g. vm200@fspbx.domain.com)
-- MESSAGE_QUERY answers startup MWI queries and explicit MWI subscriptions.
-- Registration queries are answered once per registration Call-ID, not on renewals.

local VM_PREFIX = "vm"  -- user part starts with "vm"

local Database = require "resources.functions.database"

local function log(level, msg)
    freeswitch.consoleLog(level, "[vm_blf] " .. msg .. "\n")
end

local api = freeswitch.API()
local answered_registrations = {}

---------------------------------------------------------
-- Normalize To: header or MWI account → user@domain
-- Handles: <sip:vm200@domain;param=...>, sip:..., angle brackets, params
---------------------------------------------------------
local function normalize_to_uri(to)
    if not to or to == "" then return "", "" end

    to = to:match("^%s*(.-)%s*$")
    -- trim angle brackets
    to = to:gsub("^%s*<", ""):gsub(">%s*$", "")

    -- strip leading sip: or sips:
    to = to:gsub("^sips?:", "")

    -- drop any ;params
    to = (to:match("([^;]+)")) or to

    -- now split user@domain
    local user, domain = to:match("^([^@%s<>]+)@([^@%s<>]+)$")
    return user or "", domain or ""
end

---------------------------------------------------------
-- Look up voicemail message count for mailbox@domain
--
-- Returns:
--   new_count, saved_count, mailbox_found
--   nil             -- if mailbox/domain not found or DB error
---------------------------------------------------------
local function get_voicemail_message_counts(mailbox_id, domain_name)
    local dbh = Database.new('system')
    if not (dbh and dbh:connected()) then
        log("ERR", "DB connect failed (system)")
        return nil
    end

    -----------------------------------------------------
    -- 1) Resolve domain_uuid from v_domains
    -----------------------------------------------------
    local domain_uuid, err1 = dbh:first_value(
        "select domain_uuid from v_domains where domain_name = :domain_name",
        { domain_name = domain_name }
    )

    if err1 then
        log("ERR", "DB error (domain lookup): " .. tostring(err1))
        dbh:release()
        return nil
    end

    if not domain_uuid or domain_uuid == "" then
        log("WARNING", "No domain_uuid for domain_name=" .. tostring(domain_name))
        dbh:release()
        return nil
    end

    -----------------------------------------------------
    -- 2) Resolve voicemail_uuid for this mailbox
    -----------------------------------------------------
    local voicemail_uuid, err2 = dbh:first_value([[
        select voicemail_uuid
        from v_voicemails
        where domain_uuid       = :domain_uuid
          and voicemail_id      = :vmid
          and voicemail_enabled = 'true'
    ]], {
        domain_uuid = domain_uuid,
        vmid        = mailbox_id,
    })

    if err2 then
        log("ERR", "DB error (voicemail lookup): " .. tostring(err2))
        dbh:release()
        return nil
    end

    if not voicemail_uuid or voicemail_uuid == "" then
        log("WARNING", string.format(
            "No voicemail_uuid for mailbox %s on domain %s",
            tostring(mailbox_id), tostring(domain_name)
        ))
        dbh:release()
        -- Keep clearing BLF for a removed mailbox, but do not answer MWI queries
        -- for missing or disabled mailboxes as though they were empty.
        return 0, 0, false
    end

    -----------------------------------------------------
    -- 3) Count unread and saved messages. NULL/blank are the voicemail app's
    -- unread values; retain support for the BLF daemon's existing 'new' value.
    -----------------------------------------------------
    local counts, err3 = dbh:first_row([[
        select count(case when message_status is null or message_status in ('', 'new')
                          then 1 end) as new_messages,
               count(case when message_status = 'saved' then 1 end) as saved_messages
        from v_voicemail_messages
        where voicemail_uuid = :uuid
          and domain_uuid = :domain_uuid
    ]], { uuid = voicemail_uuid, domain_uuid = domain_uuid })

    dbh:release()

    if err3 or not counts then
        log("ERR", "DB error (message count): " .. tostring(err3))
        return nil
    end

    return tonumber(counts.new_messages) or 0, tonumber(counts.saved_messages) or 0, true
end

---------------------------------------------------------
-- Answer MWI queries with the current mailbox counts.
---------------------------------------------------------
local function handle_message_query(event)
    local account = event:getHeader("Message-Account") or ""
    if not account:match("^sips?:") then return end
    local user, domain = normalize_to_uri(account)
    if user == "" or domain == "" then return end

    local profile = event:getHeader("VM-Sofia-Profile") or ""
    local call_id = event:getHeader("VM-Call-ID") or ""
    local sub_call_id = event:getHeader("VM-sub-call-id") or ""
    local registration_key
    if profile ~= "" and call_id ~= "" and sub_call_id == "" then
        registration_key = profile .. "\0" .. call_id
        local answered = answered_registrations[registration_key]
        if answered and answered[user .. "@" .. domain] then return end
    end

    local new_count, saved_count, found = get_voicemail_message_counts(user, domain)
    if not found then return end

    local reply = freeswitch.Event("MESSAGE_WAITING")
    reply:addHeader("MWI-Message-Account", "sip:" .. user .. "@" .. domain)
    reply:addHeader("MWI-Messages-Waiting", new_count > 0 and "yes" or "no")
    reply:addHeader("MWI-Voice-Message", string.format("%d/%d (0/0)", new_count, saved_count))
    if profile ~= "" then reply:addHeader("sofia-profile", profile) end
    -- Let Sofia target the original subscription or registration dialog.
    if sub_call_id ~= "" then
        reply:addHeader("sub-call-id", sub_call_id)
    elseif call_id ~= "" then
        reply:addHeader("call-id", call_id)
    end
    reply:fire()

    if registration_key then
        answered_registrations[registration_key] = answered_registrations[registration_key] or {}
        answered_registrations[registration_key][user .. "@" .. domain] = true
    end
    log("NOTICE", string.format("MESSAGE_QUERY for %s@%s new=%d saved=%d", user, domain, new_count, saved_count))
end

local function forget_registration(event)
    local profile = event:getHeader("profile-name") or ""
    local call_id = event:getHeader("call-id") or ""
    if profile ~= "" and call_id ~= "" then
        answered_registrations[profile .. "\0" .. call_id] = nil
    end
end

---------------------------------------------------------
-- Publish presence for vm<mailbox>
--
-- has_messages = true  -> LED ON  (answer-state=confirmed)
-- has_messages = false -> LED OFF (answer-state=terminated)
---------------------------------------------------------
local function publish_vm_presence(user, domain, has_messages, count)
    local ev = freeswitch.Event("PRESENCE_IN")

    ev:addHeader("proto", "sip")

    -- Optional status text: "Active (11 waiting)"
    ev:addHeader("status", string.format("Active (%d waiting)", count or 0))
    ev:addHeader("rpid", "unknown")
    ev:addHeader("event_count", "1")

    ev:addHeader("event_type", "presence")
    ev:addHeader("alt_event_type", "dialog")

    ev:addHeader("from",  user .. '@' .. domain)
    ev:addHeader("login", user .. '@' .. domain)

    local uuid = api:execute("create_uuid")
    ev:addHeader("unique-id", uuid)
    ev:addHeader("Presence-Call-Direction", "outbound")

    if has_messages then
        ev:addHeader("answer-state", "confirmed")   -- LED ON
    else
        ev:addHeader("answer-state", "terminated")  -- LED OFF
    end

    log("NOTICE", string.format(
        "Publish VM BLF: user=%s@%s has_messages=%s count=%d",
        user, domain, tostring(has_messages), count or 0
    ))

    ev:fire()
end

-- --------------------------
-- Handle MESSAGE_WAITING
-- (voicemail state changes)
-- --------------------------

-- Parse "X/Y (A/B)" -> X (new messages)
local function parse_mwi_new_count(ev)
    local vm = ev:getHeader("MWI-Voice-Message") or ""
    -- example: "2/1 (0/0)" -> "2"
    local new_str = vm:match("^(%d+)%s*/")
    return tonumber(new_str or "0") or 0
end

local function handle_mwi_event(event)
    local account = event:getHeader("MWI-Message-Account") or ""
    if account == "" then return end

    local user, domain = normalize_to_uri(account)
    if user == "" or domain == "" then
        log("DEBUG", "Ignoring MESSAGE_WAITING with bad account: " .. account)
        return
    end

    -- Our BLF AoR is vm<mailbox>@domain (vm200@domain)
    local blf_user = VM_PREFIX .. user  -- "vm" .. "200" -> "vm200"
    local key      = blf_user .. "@" .. domain


    -- yes/no if there are any NEW messages
    local waiting = (event:getHeader("MWI-Messages-Waiting") or ""):lower()
    local has_messages = (waiting == "yes")

    local new_count = parse_mwi_new_count(event)

    log("NOTICE", string.format(
        "MESSAGE_WAITING for %s@%s waiting=%s new=%d -> BLF %s",
        user, domain, tostring(has_messages), new_count, key
    ))

    publish_vm_presence(blf_user, domain, has_messages, new_count)
end

---------------------------------------------------------
-- Handle PRESENCE_PROBE for vm<mailbox>@domain
---------------------------------------------------------
local function handle_probe(event)
    local to = event:getHeader("to") or ""
    local user, domain = normalize_to_uri(to)
    if user == "" or domain == "" then
        log("DEBUG", "Ignoring PRESENCE_PROBE with unexpected To=: " .. to)
        return
    end

    -- Only handle AoRs "vm<ext>@domain".
    if user:sub(1, #VM_PREFIX) ~= VM_PREFIX then
        return
    end

    local ext = user:sub(#VM_PREFIX + 1)

    log("NOTICE", string.format("PRESENCE_PROBE Voicemail: to=%s ext=%s domain=%s", to, ext, domain))

    local count = get_voicemail_message_counts(ext, domain)
    if count == nil then
        -- DB or lookup error already logged
        return
    end

    local has_messages = (count > 0)
    publish_vm_presence(user, domain, has_messages, count)
end

---------------------------------------------------------
-- Main loop
---------------------------------------------------------
local function main()
    log("NOTICE", "vm_blf.lua starting")

    -- One consumer for SUBSCRIBE probes (phones asking "what's the state?")
    local consumer_probe = freeswitch.EventConsumer("PRESENCE_PROBE")
    -- One consumer for voicemail state changes (MESSAGE_WAITING)
    local consumer_mwi   = freeswitch.EventConsumer("MESSAGE_WAITING")
    local consumer_query = freeswitch.EventConsumer("MESSAGE_QUERY")
    local consumer_registration = freeswitch.EventConsumer("CUSTOM", "sofia::unregister")
    consumer_registration:bind("CUSTOM", "sofia::expire")

    while true do
        local did_something = false

        -- Discard ended registration sessions so this cache follows live phones.
        while true do
            local ev = consumer_registration:pop(0)
            if not ev then break end
            forget_registration(ev)
            did_something = true
        end

        while true do
            local ev = consumer_query:pop(0)
            if not ev then break end
            local ok, err = pcall(handle_message_query, ev)
            if not ok then log("ERR", "MESSAGE_QUERY failed: " .. tostring(err)) end
            did_something = true
        end

        -- handle all probes waiting
        while true do
            local ev = consumer_probe:pop(0)
            if not ev then break end
            pcall(handle_probe, ev)
            did_something = true
        end

        -- handle all mwi waiting
        while true do
            local mwi_ev = consumer_mwi:pop(0)
            if not mwi_ev then break end
            -- log("INFO", "Serialized Event: " .. mwi_ev:serialize() .. "\n")
            pcall(handle_mwi_event, mwi_ev)
            did_something = true
        end

        if not did_something then
            freeswitch.msleep(50)
        end
    end
end

main()
