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

local VM_PREFIX = "vm"  -- user part starts with "vm"

local Database = require "resources.functions.database"

local function log(level, msg)
    freeswitch.consoleLog(level, "[vm_blf] " .. msg .. "\n")
end

local api = freeswitch.API()

-- Track only vm* AoRs that actually have active subscriptions
local subscriptions = {}

---------------------------------------------------------
-- Normalize To: header or MWI account → user@domain
-- Handles: <sip:vm200@domain;param=...>, sip:..., angle brackets, params
---------------------------------------------------------
local function normalize_to_uri(to)
    if not to or to == "" then return "", "" end

    -- trim angle brackets
    to = to:gsub("^%s*<", ""):gsub(">%s*$", "")

    -- strip leading sip:
    to = to:gsub("^sip:", "")

    -- drop any ;params
    to = (to:match("([^;]+)")) or to

    -- now split user@domain
    local user, domain = to:match("^(.-)@(.-)$")
    return user or "", domain or ""
end

---------------------------------------------------------
-- Look up voicemail message count for mailbox@domain
--
-- Returns:
--   count (number)  -- NEW messages for that mailbox
--   nil             -- if mailbox/domain not found or DB error
---------------------------------------------------------
local function get_voicemail_message_count(mailbox_id, domain_name)
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
        return 0
    end

    -----------------------------------------------------
    -- 3) Count NEW messages for this voicemail_uuid
    --    Only messages with:
    --        message_status is NULL  OR  message_status = 'new'
    -----------------------------------------------------
    local count_str, err3 = dbh:first_value([[
        select count(*)
        from v_voicemail_messages
        where voicemail_uuid = :uuid
          and (message_status is null or message_status = 'new')
    ]], { uuid = voicemail_uuid })

    dbh:release()

    if err3 then
        log("ERR", "DB error (message count): " .. tostring(err3))
        return nil
    end

    local count = tonumber(count_str or "0") or 0
    return count
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
    local expires = tonumber(event:getHeader("expires") or "0")

    local user, domain = to:match("^(.-)@(.-)$")
    if user == "" or domain == "" then
        log("DEBUG", "Ignoring PRESENCE_PROBE with unexpected To=: " .. to)
        return
    end

    -- Only handle AoRs  "vm+<ext>@domain"
    if user:sub(1, #VM_PREFIX) ~= VM_PREFIX then
        return
    end

    local ext = user:sub(#VM_PREFIX + 1)

    log("NOTICE", string.format("PRESENCE_PROBE Voicemail: to=%s ext=%s domain=%s", to, ext, domain))

    local count = get_voicemail_message_count(ext, domain)
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

    while true do
        local did_something = false

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
