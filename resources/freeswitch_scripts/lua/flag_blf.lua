-- flag_blf.lua
-- BLF presence daemon for Flags (flag<number>@domain).
--   * Answers PRESENCE_PROBE with the flag's current state.
--   * Once a minute, re-checks scheduled flags with BLF enabled and publishes
--     any change, so the lamp follows the schedule without anyone pressing it.
-- Manual flags publish immediately from flag_toggle.lua and the Flags page.

require "resources.functions.config"

local Database = require "resources.functions.database"
local flag_state = require "lua.flag_state"

local FLAG_PREFIX = "flag"
local DEBUG_MODE  = false
local SCRIPT_NAME = "[flag_blf.lua]"

local api = freeswitch.API()

-- Last state published for each scheduled flag, keyed by flag UUID.
local last_published = {}

local function debug_log(level, message)
    if DEBUG_MODE then
        freeswitch.consoleLog(level, SCRIPT_NAME .. " " .. tostring(message) .. "\n")
    end
end

-- To: header -> user, domain. Handles <sip:flag1@domain;params>, sip:..., and bare user@domain.
local function normalize_to_uri(to)
    if not to or to == "" then
        return "", ""
    end

    local uri = to:match("<%s*sips?:([^>]+)>") or to:match("sips?:([^;>]+)") or to
    uri = tostring(uri):gsub("^%s+", ""):gsub("%s+$", ""):gsub("^sips?:", "")
    uri = uri:match("([^;]+)") or uri

    local user, domain = uri:match("^([^@]+)@(.+)$")
    return user or "", domain or ""
end

local function publish(user, domain, active)
    for _, event_type in ipairs({ "PRESENCE_OUT", "PRESENCE_IN" }) do
        local ev = freeswitch.Event(event_type)
        ev:addHeader("proto", "sip")
        ev:addHeader("event_type", "presence")
        ev:addHeader("alt_event_type", "dialog")
        ev:addHeader("Presence-Call-Direction", "outbound")
        ev:addHeader("from", user .. "@" .. domain)
        ev:addHeader("login", user .. "@" .. domain)
        ev:addHeader("unique-id", api:execute("create_uuid"))
        ev:addHeader("status", "Active (1 waiting)")
        ev:addHeader("event_count", "1")
        ev:addHeader("rpid", "unknown")
        ev:addHeader("answer-state", active and "confirmed" or "terminated")
        ev:fire()
    end

    debug_log("NOTICE", string.format("Published %s@%s active=%s", user, domain, tostring(active)))
end

local function connect()
    local dbh = Database.new("system")
    if not (dbh and dbh:connected()) then
        debug_log("ERR", "Database unavailable")
        return nil
    end
    return dbh
end

local function handle_probe(event)
    local expires = tonumber(event:getHeader("expires") or "0")
    if not expires or expires <= 0 then
        return
    end

    local user, domain = normalize_to_uri(event:getHeader("to") or "")
    local flag_number = user:match("^" .. FLAG_PREFIX .. "(%d+)$")
    if not flag_number or domain == "" then
        return
    end

    local dbh = connect()
    if not dbh then return end

    local flag = dbh:first_row([[
        SELECT f.uuid, f.domain_uuid, f.type, f.timezone, f.active, f.enabled, f.blf_enabled
        FROM flags f
        JOIN v_domains d ON d.domain_uuid = f.domain_uuid
        WHERE d.domain_name = :domain_name AND f.flag_number = :flag_number
        LIMIT 1
    ]], { domain_name = domain, flag_number = flag_number })

    local active = false
    if flag and flag_state.truthy(flag.blf_enabled) then
        active = flag_state.is_active(dbh, flag)
    elseif not flag then
        debug_log("WARNING", "No flag " .. flag_number .. " for domain " .. domain)
    end
    dbh:release()

    if flag then
        last_published[flag.uuid] = active
    end

    publish(user, domain, active)
end

-- Publish scheduled flags whose state changed since the last check.
local function refresh_scheduled()
    local dbh = connect()
    if not dbh then return end

    local flags = {}
    dbh:query([[
        SELECT f.uuid, f.domain_uuid, f.flag_number, f.type, f.timezone, f.active, f.enabled, d.domain_name
        FROM flags f
        JOIN v_domains d ON d.domain_uuid = f.domain_uuid
        WHERE f.type = 'scheduled' AND f.blf_enabled = true
    ]], {}, function(row)
        table.insert(flags, row)
    end)

    local seen = {}
    for _, flag in ipairs(flags) do
        seen[flag.uuid] = true
        local active = flag_state.is_active(dbh, flag)
        if last_published[flag.uuid] ~= active then
            last_published[flag.uuid] = active
            publish(FLAG_PREFIX .. flag.flag_number, flag.domain_name, active)
        end
    end
    dbh:release()

    -- Forget flags that were deleted or no longer use BLF.
    for uuid in pairs(last_published) do
        if not seen[uuid] then
            last_published[uuid] = nil
        end
    end
end

local function main()
    debug_log("NOTICE", "Starting flag BLF daemon")

    local consumer = freeswitch.EventConsumer("PRESENCE_PROBE")
    local last_minute = nil

    while true do
        local did_something = false

        -- Handle every probe waiting.
        while true do
            local ev = consumer:pop(0)
            if not ev then break end
            local ok, err = pcall(handle_probe, ev)
            if not ok then
                freeswitch.consoleLog("ERR", SCRIPT_NAME .. " probe failed: " .. tostring(err) .. "\n")
            end
            did_something = true
        end

        local minute = math.floor(os.time() / 60)
        if minute ~= last_minute then
            last_minute = minute
            local ok, err = pcall(refresh_scheduled)
            if not ok then
                freeswitch.consoleLog("ERR", SCRIPT_NAME .. " schedule refresh failed: " .. tostring(err) .. "\n")
            end
        end

        if not did_something then
            freeswitch.msleep(50)
        end
    end
end

main()
