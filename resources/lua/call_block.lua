-- call_block.lua
-- Runtime call-block evaluator.
--
-- The database is the source of truth. Redis stores short-lived, scoped rule
-- lists so the hot call path does not need to query Postgres on every call.
-- If Redis misses or fails, this script falls back to Postgres. If both Redis
-- and Postgres are unavailable, the script fails open and allows the call.
--
-- Debugging: set DEBUG_MODE = true to log the important runtime decisions.

DEBUG_MODE = true

local json = require "resources.functions.lunajson"
require "resources.functions.base64"

local cache_ttl = 43200
local redis_profile = "default"
local api = freeswitch.API()

local function log(level, message)
    if DEBUG_MODE then
        freeswitch.consoleLog(level, "[call_block.lua] " .. tostring(message) .. "\n")
    end
end

local function truncate(value, length)
    value = tostring(value or "")
    length = length or 240

    if string.len(value) <= length then
        return value
    end

    return string.sub(value, 1, length) .. "...[truncated]"
end

local function blank(value)
    return value == nil or tostring(value) == "" or tostring(value) == "_undef_"
end

local function scope_label(scope)
    if scope == "all" then
        return "all extensions"
    end

    return "extension " .. tostring(scope)
end

local function rule_word(count)
    return tonumber(count) == 1 and "rule" or "rules"
end

-- Execute a raw Redis command through FreeSWITCH mod_hiredis.
-- Expected syntax in fs_cli is: hiredis_raw default GET key
local function redis(command)
    local ok, response = pcall(function()
        return api:execute("hiredis_raw", redis_profile .. " " .. command)
    end)

    if not ok then
        log("WARNING", "Redis did not answer command '" .. truncate(command) .. "'. The script will try the database if it needs these rules.")
        return nil
    end

    if blank(response) then
        return nil
    end

    return response
end

-- Laravel increments this version after rule writes. Old rule-list keys expire
-- naturally, which avoids needing to enumerate every possible scoped key.
local function current_version(domain_uuid)
    local version = redis("GET call_block:version:" .. domain_uuid)
    version = blank(version) and "1" or tostring(version)
    return version
end

-- Scope is either the current extension_uuid or "all" for domain-wide rules.
local function cache_key(version, domain_uuid, direction, scope)
    return "call_block:rules:v" .. version .. ":" .. domain_uuid .. ":" .. direction .. ":" .. scope
end

-- Cache values are base64(JSON) because Redis raw commands are whitespace
-- sensitive and base64 keeps the payload command-safe.
local function decode_rules(encoded)
    if blank(encoded) then
        return nil
    end

    local ok_decode, decoded = pcall(function()
        return base64.decode(encoded)
    end)
    if not ok_decode or blank(decoded) then
        log("WARNING", "The cached call block rules could not be decoded. The script will rebuild them from the database.")
        return nil
    end

    local ok_json, rules = pcall(function()
        return json.decode(decoded)
    end)
    if not ok_json or type(rules) ~= "table" then
        log("WARNING", "The cached call block rules were not valid JSON. The script will rebuild them from the database.")
        return nil
    end

    return rules
end

local function encode_rules(rules)
    local ok_json, encoded_json = pcall(function()
        return json.encode(rules)
    end)
    if not ok_json or blank(encoded_json) then
        log("WARNING", "The database rules were loaded, but could not be encoded for Redis.")
        return nil
    end

    local ok_base64, encoded = pcall(function()
        return base64.encode(encoded_json)
    end)
    if not ok_base64 or blank(encoded) then
        log("WARNING", "The database rules were loaded, but could not be prepared for Redis.")
        return nil
    end

    return encoded
end

-- Build a scoped list of enabled rules from Postgres. Matching is still done
-- in Lua so Redis can cache the full scoped rule list instead of one decision
-- per caller ID number.
local function load_rules_from_database(domain_uuid, direction, scope)
    local ok, rules = pcall(function()
        local Database = require "resources.functions.database"
        local dbh = Database.new("system")
        assert(dbh:connected())

        local rows = {}
        local sql = "select call_block_uuid, extension_uuid, call_block_name, call_block_country_code, call_block_number, call_block_app, call_block_data "
            .. "from v_call_block "
            .. "where (domain_uuid = :domain_uuid or domain_uuid is null) "
            .. "and call_block_enabled = 'true' "
            .. "and call_block_direction = :call_block_direction "
        local params = {
            domain_uuid = domain_uuid,
            call_block_direction = direction,
        }

        if scope == "all" then
            sql = sql .. "and extension_uuid is null "
        else
            sql = sql .. "and extension_uuid = :extension_uuid "
            params["extension_uuid"] = scope
        end

        sql = sql .. "order by call_block_number desc nulls last, call_block_name desc nulls last, call_block_uuid "

        dbh:query(sql, params, function(row)
            table.insert(rows, {
                call_block_uuid = row.call_block_uuid,
                extension_uuid = row.extension_uuid,
                call_block_name = row.call_block_name,
                call_block_country_code = row.call_block_country_code,
                call_block_number = row.call_block_number,
                call_block_app = row.call_block_app,
                call_block_data = row.call_block_data,
            })
        end)

        dbh:release()
        return rows
    end)

    if not ok then
        log("ERR", "The database lookup for " .. scope_label(scope) .. " failed. Calls will not be blocked from this rule scope. Error: " .. tostring(rules))
        return nil
    end

    return rules
end

-- Read from Redis first. On miss, rebuild from Postgres and repopulate Redis.
local function load_rules(domain_uuid, direction, scope, version)
    local key = cache_key(version, domain_uuid, direction, scope)
    local cached = redis("GET " .. key)
    local rules = decode_rules(cached)

    if rules ~= nil then
        log("NOTICE", "Using " .. tostring(#rules) .. " cached " .. tostring(direction) .. " call block " .. rule_word(#rules) .. " for " .. scope_label(scope) .. ".")
        return rules
    end

    rules = load_rules_from_database(domain_uuid, direction, scope)
    if rules == nil then
        log("WARNING", "No usable call block rules were available for " .. scope_label(scope) .. ". This scope is failing open.")
        return nil
    end

    local encoded = encode_rules(rules)
    if encoded ~= nil then
        redis("SETEX " .. key .. " " .. cache_ttl .. " " .. encoded)
    end

    log("NOTICE", "Loaded " .. tostring(#rules) .. " " .. tostring(direction) .. " call block " .. rule_word(#rules) .. " for " .. scope_label(scope) .. " from the database and refreshed Redis.")

    return rules
end

local function trim(value)
    value = tostring(value or "")
    value = value:gsub("^%s+", "")
    value = value:gsub("%s+$", "")

    return value
end

local function starts_with(value, prefix)
    return string.sub(value, 1, string.len(prefix)) == prefix
end

local function digits_only(value)
    return tostring(value or ""):gsub("%D+", "")
end

local function normalized_name(value)
    value = trim(value)

    if value == "" then
        return ""
    end

    -- Caller ID names are messy in practice: CNAM may add quotes,
    -- punctuation, repeated spaces, or arrive in a different case. Normalize
    -- those differences before comparing name-based block rules.
    value = value:gsub("^['\"]+", "")
    value = value:gsub("['\"]+$", "")
    value = value:gsub("[^%w]+", " ")
    value = value:gsub("_+", " ")
    value = value:gsub("%s+", " ")

    return string.lower(trim(value))
end

local function add_name_candidate(candidates, seen, value)
    local normalized = normalized_name(value)

    if normalized ~= "" and not seen[normalized] then
        table.insert(candidates, trim(value))
        seen[normalized] = true
    end
end

local function caller_name_candidates()
    local candidates = {}
    local seen = {}

    -- CNAM lookup updates effective_caller_id_name, while caller_id_name may
    -- still be the original number. Check the effective display name first.
    add_name_candidate(candidates, seen, session:getVariable("effective_caller_id_name"))
    add_name_candidate(candidates, seen, session:getVariable("caller_id_name"))
    add_name_candidate(candidates, seen, session:getVariable("origination_caller_id_name"))
    add_name_candidate(candidates, seen, session:getVariable("origination_callee_id_name"))
    add_name_candidate(candidates, seen, session:getVariable("sip_from_display"))

    return candidates
end

local function caller_name_for_log(caller_names, number)
    for _, caller_name in ipairs(caller_names) do
        if not blank(caller_name) and normalized_name(caller_name) ~= normalized_name(number) then
            return caller_name
        end
    end

    return nil
end

-- Match a call-block name as either an exact normalized name or a full
-- word/phrase inside the caller name. This lets a rule named "DEXTER" block
-- "ALLEN DEXTER" without also matching unrelated names like "DEXTERITY".
local function name_matches_rule(rule_name, caller_name)
    local rule_normalized = normalized_name(rule_name)
    local caller_normalized = normalized_name(caller_name)

    if rule_normalized == "" or caller_normalized == "" then
        return false
    end

    if rule_normalized == caller_normalized then
        return true
    end

    return string.find(" " .. caller_normalized .. " ", " " .. rule_normalized .. " ", 1, true) ~= nil
end

local function any_name_matches(rule_name, caller_names)
    if type(caller_names) ~= "table" then
        return false
    end

    for _, caller_name in ipairs(caller_names) do
        if name_matches_rule(rule_name, caller_name) then
            return true
        end
    end

    return false
end

local function add_candidate(candidates, value)
    if not blank(value) then
        candidates[tostring(value)] = true
    end
end

local function normalize_sip_or_tel_number(value)
    value = trim(value)

    if value == "" then
        return value
    end

    local lower = string.lower(value)
    if starts_with(lower, "sip:") then
        value = string.sub(value, 5)
    elseif starts_with(lower, "tel:") then
        value = string.sub(value, 5)
    end

    local user_part = value:match("^([^@]+)@")
    if user_part ~= nil then
        value = user_part
    end

    return trim(value)
end

local function add_nanp_forms(candidates, digits)
    if string.len(digits) == 10 then
        add_candidate(candidates, digits)
        add_candidate(candidates, "1" .. digits)
        add_candidate(candidates, "+1" .. digits)
        return
    end

    if string.len(digits) == 11 and starts_with(digits, "1") then
        add_candidate(candidates, digits)
        add_candidate(candidates, "+" .. digits)
        add_candidate(candidates, string.sub(digits, 2))
    end
end

local function add_international_prefix_forms(candidates, digits)
    if starts_with(digits, "011") and string.len(digits) > 3 then
        local e164_digits = string.sub(digits, 4)
        add_candidate(candidates, e164_digits)
        add_candidate(candidates, "+" .. e164_digits)
    end

    if starts_with(digits, "00") and string.len(digits) > 2 then
        local e164_digits = string.sub(digits, 3)
        add_candidate(candidates, e164_digits)
        add_candidate(candidates, "+" .. e164_digits)
    end
end

local function add_number_forms(candidates, raw_number, country_code)
    local raw = normalize_sip_or_tel_number(raw_number)
    local digits = digits_only(raw)
    local country_digits = digits_only(country_code)

    if blank(raw) and blank(digits) then
        return
    end

    add_candidate(candidates, raw)

    if not blank(digits) then
        add_candidate(candidates, digits)

        if starts_with(raw, "+") then
            add_candidate(candidates, "+" .. digits)
        end

        add_international_prefix_forms(candidates, digits)
        add_nanp_forms(candidates, digits)
    end

    if not blank(country_digits) and not blank(digits) then
        local combined = digits

        if not starts_with(digits, country_digits) then
            combined = country_digits .. digits
        end

        add_candidate(candidates, combined)
        add_candidate(candidates, "+" .. combined)

        if country_digits == "1" then
            add_nanp_forms(candidates, combined)
        end
    end
end

local function number_candidates(raw_number, country_code)
    local candidates = {}
    add_number_forms(candidates, raw_number, country_code)

    return candidates
end

-- Match numbers after conservative normalization:
-- - remove formatting characters
-- - match raw/local forms exactly after cleanup
-- - match E.164 forms with or without +
-- - convert 011/00 international prefixes to E.164 candidates
-- - add 10/11 digit NANP equivalents only for clearly NANP-shaped numbers
local function number_matches(rule, number)
    if blank(rule.call_block_number) or blank(number) then
        return false
    end

    local rule_candidates = number_candidates(rule.call_block_number, rule.call_block_country_code)
    local call_candidates = number_candidates(number, nil)

    for candidate, _ in pairs(call_candidates) do
        if rule_candidates[candidate] then
            return true
        end
    end

    return false
end

-- Preserve legacy matching behavior:
-- 1. caller ID name + number
-- 2. number only
-- 3. caller ID name only
-- Caller names are normalized and compared as whole words/phrases so CNAM
-- results like "ALLEN DEXTER" can match a shorter rule like "DEXTER".
local function rule_matches(rule, caller_names, number)
    local has_name = not blank(rule.call_block_name)
    local has_number = not blank(rule.call_block_number)
    local name_match = has_name and any_name_matches(rule.call_block_name, caller_names)
    local number_match = has_number and number_matches(rule, number)

    local matched = (has_name and has_number and name_match and number_match)
        or ((not has_name) and has_number and number_match)
        or (has_name and (not has_number) and name_match)

    return matched
end

-- Rules are loaded in deterministic SQL order. First match wins.
local function find_matching_rule(rules, caller_names, number)
    if type(rules) ~= "table" then
        return nil
    end

    for _, rule in ipairs(rules) do
        if rule_matches(rule, caller_names, number) then
            return rule
        end
    end

    return nil
end

-- Keep statistics accurate under concurrent calls.
local function update_count(call_block_uuid)
    local ok, err = pcall(function()
        local Database = require "resources.functions.database"
        local dbh = Database.new("system")
        assert(dbh:connected())
        local sql = "update v_call_block "
            .. "set call_block_count = coalesce(call_block_count, 0) + 1 "
            .. "where call_block_uuid = :call_block_uuid "
        dbh:query(sql, { call_block_uuid = call_block_uuid })
        dbh:release()
    end)

    if not ok then
        log("ERR", "The call was blocked, but the hit counter could not be updated for rule " .. tostring(call_block_uuid) .. ". Error: " .. tostring(err))
    end
end

-- Apply the configured action after a rule match. These actions all end the
-- current call block evaluation immediately.
local function apply_action(rule, scope)
    if not session:ready() or rule == nil or blank(rule.call_block_app) then
        return false
    end

    if rule.call_block_app ~= "busy" and rule.call_block_app ~= "reject" and rule.call_block_app ~= "voicemail" then
        log("WARNING", "The call matched rule " .. tostring(rule.call_block_uuid) .. ", but action '" .. tostring(rule.call_block_app) .. "' is no longer supported. The call will continue.")
        return false
    end

    if rule.call_block_app == "voicemail" and blank(rule.call_block_data) then
        log("WARNING", "The call matched rule " .. tostring(rule.call_block_uuid) .. ", but no voicemail mailbox was selected. The call will continue.")
        return false
    end

    local voicemail_context = nil
    if rule.call_block_app == "voicemail" then
        voicemail_context = session:getVariable("context")

        if blank(voicemail_context) then
            voicemail_context = session:getVariable("domain_name")
        end

        if blank(voicemail_context) then
            log("WARNING", "The call matched rule " .. tostring(rule.call_block_uuid) .. ", but the call has no context for voicemail transfer. The call will continue.")
            return false
        end
    end

    log("NOTICE", "The call matched rule " .. tostring(rule.call_block_uuid) .. " for " .. scope_label(scope) .. ". Applying action: " .. tostring(rule.call_block_app) .. ".")

    session:execute("set", "call_block=true")
    session:execute("set", "call_block_uuid=" .. rule.call_block_uuid)
    session:execute("set", "call_block_app=" .. rule.call_block_app)

    if not blank(rule.call_block_data) then
        session:execute("set", "call_block_data=" .. rule.call_block_data)
    end

    if rule.call_block_app == "busy" then
        session:execute("respond", "486")
        return true
    end

    if rule.call_block_app == "reject" then
        session:execute("respond", "600")
        return true
    end

    if rule.call_block_app == "voicemail" then
        session:execute("transfer", "*99" .. rule.call_block_data .. " XML " .. voicemail_context)
        return true
    end

    return false
end

-- Pull call context from the current FreeSWITCH session.
local domain_uuid = nil
local call_direction = nil
local caller_names = {}
local caller_id_number = nil
local destination_number = nil
local call_block = nil
local extension_uuid = nil

if session:ready() then
    domain_uuid = session:getVariable("domain_uuid")
    call_direction = session:getVariable("call_direction")
    caller_names = caller_name_candidates()
    caller_id_number = session:getVariable("caller_id_number")
    destination_number = session:getVariable("destination_number")
    call_block = session:getVariable("call_block")
    extension_uuid = session:getVariable("extension_uuid")
end

if blank(domain_uuid) then
    log("WARNING", "The call has no domain UUID, so call blocking was skipped.")
    return
end

if blank(call_direction) then
    log("WARNING", "The call has no direction, so call blocking was skipped.")
    return
end

if call_block == "true" then
    log("NOTICE", "This call was already marked as blocked, so the script skipped a second evaluation.")
    return
end

-- Inbound blocks compare against caller_id_number. Outbound blocks compare
-- against destination_number.
local number = call_direction == "outbound" and destination_number or caller_id_number
local version = current_version(domain_uuid)
local matching_rule = nil
local matching_scope = nil
local caller_name_log = caller_name_for_log(caller_names, number)
local call_description = "number " .. tostring(number)

if not blank(caller_name_log) then
    call_description = call_description .. " and caller name " .. tostring(caller_name_log)
end

log("NOTICE", "Checking " .. tostring(call_direction) .. " call block rules for " .. call_description .. ". Cache version " .. tostring(version) .. ".")

-- Extension-specific rules win over domain-wide rules.
if not blank(extension_uuid) then
    local extension_rules = load_rules(domain_uuid, call_direction, extension_uuid, version)
    matching_rule = find_matching_rule(extension_rules, caller_names, number)
    if matching_rule ~= nil then
        matching_scope = extension_uuid
    end
end

-- Domain-wide rules use the "all" scope and apply when no extension-specific
-- rule matched, or when the call has no extension_uuid.
if matching_rule == nil then
    local all_rules = load_rules(domain_uuid, call_direction, "all", version)
    matching_rule = find_matching_rule(all_rules, caller_names, number)
    if matching_rule ~= nil then
        matching_scope = "all"
    end
end

if matching_rule ~= nil then
    if apply_action(matching_rule, matching_scope) then
        update_count(matching_rule.call_block_uuid)
    end
else
    log("NOTICE", "No call block rule matched " .. call_description .. ". The call will continue.")
end
