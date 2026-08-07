-- Enable/Disable debug mode globally
DEBUG_MODE = false  -- Set to false to disable debug logs

-- Bandwidth treats any request containing the test parameter as non-billable
-- and returns a random caller name. Test responses must never be cached.
local BANDWIDTH_TEST_MODE = false

-- Debug logging function
function debug_log(level, message)
    if DEBUG_MODE then
        freeswitch.consoleLog(level, message .. "\n")
    end
end

local api = freeswitch.API()

local function shell_quote(value)
    return "'" .. tostring(value):gsub("'", "'\\''") .. "'"
end

local function command_succeeded(ok, _, status)
    if type(ok) == "number" then
        return ok == 0
    end

    return ok == true and (status == nil or status == 0)
end

local function url_encode(value)
    return (tostring(value):gsub("([^%w%-_%.~])", function(character)
        return string.format("%%%02X", string.byte(character))
    end))
end

local function bandwidth_lookup(digits)
    local company_id = api:executeString("global_getvar bandwidth_cnam_company_id")
    local password = api:executeString("global_getvar bandwidth_cnam_password")

    if not company_id or company_id == "" or company_id == "_undef_" then
        debug_log("ERR", "[cnam_lookup_bandwidth.lua] bandwidth_cnam_company_id is not set")
        return nil
    end

    if not company_id:match("^%d+$") then
        debug_log("ERR", "[cnam_lookup_bandwidth.lua] bandwidth_cnam_company_id is invalid")
        return nil
    end

    if not password or password == "" or password == "_undef_" then
        debug_log("ERR", "[cnam_lookup_bandwidth.lua] bandwidth_cnam_password is not set")
        return nil
    end

    local e164 = "1" .. digits
    local query = {
        "companyId=" .. url_encode(company_id),
        "password=" .. url_encode(password),
        "number=" .. e164,
    }

    if BANDWIDTH_TEST_MODE then
        table.insert(query, "test=true")
    end

    local url = "https://cnam.dashcs.com/?" .. table.concat(query, "&")
    local cmd = string.format(
        "/usr/bin/curl --silent --show-error --fail-with-body --connect-timeout 1 --max-time 3 --globoff %s",
        shell_quote(url)
    )

    debug_log("INFO", "[cnam_lookup_bandwidth.lua] Querying Bandwidth for +" .. e164 ..
        (BANDWIDTH_TEST_MODE and " in test mode" or ""))

    local handle = io.popen(cmd)
    if not handle then
        debug_log("ERR", "[cnam_lookup_bandwidth.lua] Failed to execute curl")
        return nil
    end

    local body = handle:read("*a") or ""
    local close_ok, close_reason, close_status = handle:close()

    if not command_succeeded(close_ok, close_reason, close_status) then
        debug_log("ERR", "[cnam_lookup_bandwidth.lua] Bandwidth request failed")
        return nil
    end

    local name = body:match("^%s*(.-)%s*$")
    local normalized_name = name and name:upper() or ""

    debug_log("INFO", "[cnam_lookup_bandwidth.lua] Bandwidth raw response: " .. body)

    if not name or name == "" or normalized_name == "UNKNOWN" or normalized_name == "UNAVAILABLE" then
        debug_log("INFO", "[cnam_lookup_bandwidth.lua] No CNAM returned by Bandwidth for +" .. e164)
        return nil
    end

    return name
end

debug_log("INFO", "[cnam_lookup_bandwidth.lua] Executing Bandwidth CNAM Lookup Lua Script...")

local uuid = argv[1]
if not uuid or uuid == "" then
    return
end

-- 1) Pull and normalize the caller number
local raw = api:executeString("uuid_getvar " .. uuid .. " caller_id_number")
debug_log("INFO", "[cnam_lookup_bandwidth.lua] Raw caller_id_number: " .. tostring(raw))
if not raw or raw == "" or raw == "_undef_" then
    return
end

local digits = raw:gsub("%D", "")
if #digits == 11 and digits:sub(1, 1) == "1" then
    digits = digits:sub(2)
end

-- Bandwidth CNAM Per DIP supports NANP numbers only.
if not digits:match("^[2-9]%d%d[2-9]%d%d%d%d%d%d$") then
    debug_log("WARNING", "[cnam_lookup_bandwidth.lua] Caller ID is not a valid NANP number")
    return
end
debug_log("INFO", "[cnam_lookup_bandwidth.lua] Normalized to 10 digits: " .. digits)

-- 2) Check the database cache in production only. Test names are random, so
-- test mode must always reach Bandwidth and must not read or write the cache.
local dbh
local cached_name, cached_ts

if not BANDWIDTH_TEST_MODE then
    local Database = require "resources.functions.database"
    dbh = Database.new("system")

    local sql_check = [[
        SELECT cnam, extract(epoch from date) AS date
        FROM v_cnam
        WHERE phone_number = :phone
        ORDER BY date DESC NULLS LAST
        LIMIT 1
    ]]
    local params = { phone = digits }

    debug_log("INFO", "[cnam_lookup_bandwidth.lua] Querying local database: " .. sql_check)
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
                "[cnam_lookup_bandwidth.lua] Using cached CNAM from local database '%s' (age %.1f days)",
                cached_name, age / 86400
            ))
        else
            debug_log("INFO", string.format(
                "[cnam_lookup_bandwidth.lua] Cache in local database is stale (%.1f days), deleting and refreshing",
                age / 86400
            ))
            local sql_del = "DELETE FROM v_cnam WHERE phone_number = :phone"
            debug_log("INFO", "[cnam_lookup_bandwidth.lua] Deleting stale cache: " .. sql_del)
            dbh:query(sql_del, params)
            cached_name = nil
        end
    end
else
    debug_log("INFO", "[cnam_lookup_bandwidth.lua] Test mode enabled; bypassing local cache")
end

-- 3) If no valid cache, query Bandwidth directly
local name = cached_name
local fetched_fresh = false

if not name or name == "" then
    debug_log("INFO", "[cnam_lookup_bandwidth.lua] No valid cache, querying Bandwidth directly")

    name = bandwidth_lookup(digits)
    debug_log("INFO", "[cnam_lookup_bandwidth.lua] Bandwidth returned: " .. tostring(name))

    if name and #name > 0 then
        fetched_fresh = true
    end
end

-- 4) Apply to channel first
if name and #name > 0 and name ~= "UNKNOWN" then
    api:executeString("uuid_setvar " .. uuid .. " ignore_display_updates false")
    api:executeString("uuid_setvar " .. uuid .. " origination_callee_id_name " .. name)
    api:executeString("uuid_setvar " .. uuid .. " origination_callee_id_number " .. digits)
    api:executeString("uuid_setvar " .. uuid .. " caller_id_name " .. name)
    api:executeString("uuid_setvar " .. uuid .. " effective_caller_id_name " .. name)
    api:executeString("uuid_display " .. uuid .. " " .. name .. "|" .. digits)

    local source = "Database cache"
    if fetched_fresh then
        source = BANDWIDTH_TEST_MODE and "Bandwidth test result" or "Bandwidth API result"
    end

    freeswitch.consoleLog("INFO", "[cnam_lookup_bandwidth.lua] Updated display name to " .. name ..
        " (" .. source .. ")")
end

-- 5) Cache fresh production results only
if not BANDWIDTH_TEST_MODE and fetched_fresh and name and #name > 0 then
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

    debug_log("INFO", "[cnam_lookup_bandwidth.lua] Inserting new cache: " .. sql_ins)
    dbh:query(sql_ins, ins_params)
    debug_log("INFO", "[cnam_lookup_bandwidth.lua] Cached CNAM for " .. digits .. " -> " .. name)
end
