-- Enable/Disable debug mode globally
DEBUG_MODE = true  -- Set to false to disable debug logs

-- Debug logging function
function debug_log(level, message)
    if DEBUG_MODE then
        freeswitch.consoleLog(level, message .. "\n")
    end
end

debug_log("INFO", "[cnam_lookup.lua] Executing CNAM Lookup Lua Script...")

local api = freeswitch.API()
local uuid = argv[1]
if not uuid or uuid == "" then
  return
end

-- 1) Pull & normalize caller number
local raw = api:executeString("uuid_getvar "..uuid.." caller_id_number")
debug_log("INFO", "[cnam_lookup.lua] Raw caller_id_number: "..tostring(raw))
if not raw or raw == "" then return end

local digits = raw:gsub("%D","")               -- strip non-digits
if #digits > 10 then
  digits = digits:sub(-10)                    -- keep last 10
end
debug_log("INFO", "[cnam_lookup.lua] Normalized to 10 digits: "..digits)

-- 2) Check DB cache
local Database = require "resources.functions.database"
local dbh      = Database.new("system")

local cached_name, cached_ts
local sql_check  = [[
  SELECT cnam, extract(epoch from date) AS date 
    FROM v_cnam 
   WHERE phone_number LIKE :phone
]]
local params     = { phone = "%" .. digits .. "%" }

debug_log("INFO", "[cnam_lookup.lua] Querying local database: "..sql_check)
dbh:query(sql_check, params, function(row)
  cached_name = row.cnam
  cached_ts   = tonumber(row.date)
end)

local now = os.time()
local TTL = 90 * 24 * 3600  -- 90 days

if cached_name and cached_ts then
  local age = now - cached_ts
  if age < TTL then
    debug_log("INFO", string.format(
      "[cnam_lookup.lua] Using cached CNAM from local database '%s' (age %.1f days)", cached_name, age/86400
    ))
  else
    debug_log("INFO", string.format(
      "[cnam_lookup.lua] Cache in local database is stale (%.1f days), deleting and refreshing", age/86400
    ))
    local sql_del = "DELETE FROM v_cnam WHERE phone_number LIKE :phone"
    debug_log("INFO", "[cnam_lookup.lua] Deleting stale cache: "..sql_del)
    dbh:query(sql_del, params)
    cached_name = nil
  end
end

-- 3) If no valid cache, run cidlookup and insert
local name = cached_name
if not name or name == "" then
  debug_log("INFO", "[cnam_lookup.lua] No valid cache, running mod_cidlookup")
  if api:executeString("module_exists mod_cidlookup") == "true" then
    name = api:executeString("cidlookup "..digits)
    debug_log("INFO", "[cnam_lookup.lua] cidlookup returned: "..tostring(name))

    if name and #name > 0 then
      local new_uuid = api:executeString("create_uuid")
      local sql_ins  = [[
        INSERT INTO v_cnam (cnam_uuid,phone_number,cnam,date)
             VALUES(:uuid, :phone, :cnam, NOW())
      ]]
      local ins_params = {
        uuid  = new_uuid,
        phone = digits,
        cnam  = name
      }
      debug_log("INFO", "[cnam_lookup.lua] Inserting new cache: "..sql_ins)
      dbh:query(sql_ins, ins_params)
      debug_log("INFO", "[cnam_lookup.lua] Cached CNAM for "..digits.." -> "..name)
    end
  else
    debug_log("WARNING", "[cnam_lookup.lua] mod_cidlookup not loaded")
  end
end

-- 4) Apply to channel if we got a name
if name and #name > 0 and name ~= "UNKNOWN" then
  api:executeString("uuid_setvar "..uuid.." effective_caller_id_name "..name)
  debug_log("INFO", "[cnam_lookup.lua] Set effective_caller_id_name to "..name)
end
