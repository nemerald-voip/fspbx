-- Enable/Disable debug mode globally
DEBUG_MODE = false  -- Set to false to disable debug logs

-- Debug logging function
function debug_log(level, message)
    if DEBUG_MODE then
        freeswitch.consoleLog(level, message .. "\n")
    end
end

local api = freeswitch.API()

local function telnyx_lookup(digits)
    local telnyx_api_key = api:executeString("global_getvar telnyx_api_key")

    if not telnyx_api_key or telnyx_api_key == "" or telnyx_api_key == "_undef_" then
        debug_log("ERR", "[cnam_lookup.lua] telnyx_api_key is not set")
        return nil
    end

    local e164 = "+1" .. digits
    local url = "https://api.telnyx.com/v2/number_lookup/" .. e164 .. "?type=caller-name"

    local cmd = string.format(
        [[curl -sS --max-time 1 --globoff -H "Accept: application/json" -H "Authorization: Bearer %s" "%s"]],
        telnyx_api_key,
        url
    )

    debug_log("INFO", "[cnam_lookup.lua] Querying Telnyx for " .. e164)

    local handle = io.popen(cmd)
    if not handle then
        debug_log("ERR", "[cnam_lookup.lua] Failed to execute curl")
        return nil
    end

    local body = handle:read("*a") or ""
    handle:close()

    debug_log("INFO", "[cnam_lookup.lua] Telnyx raw response: " .. body)

    local json_ok, json = pcall(require, "resources.functions.lunajson")
    if not json_ok or not json then
        debug_log("ERR", "[cnam_lookup.lua] Lua JSON parser is not available")
        return nil
    end

    local decode_ok, decoded = pcall(json.decode, body)
    if not decode_ok or not decoded then
        debug_log("ERR", "[cnam_lookup.lua] Failed to decode Telnyx JSON response")
        return nil
    end

    local caller_name_data = decoded.data and decoded.data.caller_name or nil
    local name = caller_name_data and caller_name_data.caller_name or nil

    if not name or name == "" or name == "UNKNOWN" or name == "UNAVAILABLE" then
        debug_log("INFO", "[cnam_lookup.lua] No CNAM returned by Telnyx for " .. e164)
        return nil
    end

    return name
end

debug_log("INFO", "[cnam_lookup.lua] Executing CNAM Lookup Lua Script...")

local uuid = argv[1]
if not uuid or uuid == "" then
    return
end

-- 1) Pull & normalize caller number
local raw = api:executeString("uuid_getvar " .. uuid .. " caller_id_number")
debug_log("INFO", "[cnam_lookup.lua] Raw caller_id_number: " .. tostring(raw))
if not raw or raw == "" then
    return
end

local digits = raw:gsub("%D", "") -- strip non-digits
if #digits > 10 then
    digits = digits:sub(-10) -- keep last 10
end
debug_log("INFO", "[cnam_lookup.lua] Normalized to 10 digits: " .. digits)

-- 2) Check DB cache
local Database = require "resources.functions.database"
local dbh = Database.new("system")

local cached_name, cached_ts
local sql_check = [[
    SELECT cnam, extract(epoch from date) AS date
    FROM v_cnam
    WHERE phone_number LIKE :phone
]]
local params = { phone = "%" .. digits .. "%" }

debug_log("INFO", "[cnam_lookup.lua] Querying local database: " .. sql_check)
dbh:query(sql_check, params, function(row)
    cached_name = row.cnam
    cached_ts = tonumber(row.date)
end)

local now = os.time()
local TTL = 90 * 24 * 3600 -- 90 days

if cached_name and cached_ts then
    local age = now - cached_ts
    if age < TTL then
        debug_log("INFO", string.format(
            "[cnam_lookup.lua] Using cached CNAM from local database '%s' (age %.1f days)",
            cached_name, age / 86400
        ))
    else
        debug_log("INFO", string.format(
            "[cnam_lookup.lua] Cache in local database is stale (%.1f days), deleting and refreshing",
            age / 86400
        ))
        local sql_del = "DELETE FROM v_cnam WHERE phone_number LIKE :phone"
        debug_log("INFO", "[cnam_lookup.lua] Deleting stale cache: " .. sql_del)
        dbh:query(sql_del, params)
        cached_name = nil
    end
end

-- 3) If no valid cache, query Telnyx directly
local name = cached_name
local fetched_fresh = false

if not name or name == "" then
    debug_log("INFO", "[cnam_lookup.lua] No valid cache, querying Telnyx directly")

    name = telnyx_lookup(digits)
    debug_log("INFO", "[cnam_lookup.lua] Telnyx returned: " .. tostring(name))

    if name and #name > 0 then
        fetched_fresh = true
    end
end

-- 4) Apply to channel first
if name and #name > 0 and name ~= "UNKNOWN" then
    api:executeString("uuid_setvar " .. uuid .. " ignore_display_updates false")
    api:executeString("uuid_setvar " .. uuid .. " origination_callee_id_name " .. name)
    api:executeString("uuid_setvar " .. uuid .. " origination_callee_id_number " .. digits)
    api:executeString("uuid_setvar " .. uuid .. " effective_caller_id_name " .. name)
    api:executeString("uuid_display " .. uuid .. " " .. name .. "|" .. digits)
    freeswitch.consoleLog("INFO", "[cnam_lookup.lua] Updated display name to " .. name .. " (" .. (fetched_fresh and "API result" or "Database cache") .. ")")
end

-- 5) Cache only after display vars are set
if fetched_fresh and name and #name > 0 then
    local new_uuid = api:executeString("create_uuid")
    local sql_ins = [[
        INSERT INTO v_cnam (cnam_uuid, phone_number, cnam, date)
        VALUES (:uuid, :phone, :cnam, NOW())
    ]]

    local ins_params = {
        uuid = new_uuid,
        phone = digits,
        cnam = name
    }

    debug_log("INFO", "[cnam_lookup.lua] Inserting new cache: " .. sql_ins)
    dbh:query(sql_ins, ins_params)
    debug_log("INFO", "[cnam_lookup.lua] Cached CNAM for " .. digits .. " -> " .. name)
end