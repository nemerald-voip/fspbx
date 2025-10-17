-- hotel_room_status_update.lua
-- Updates housekeeping_status for the calling room using a 1–2 digit code.

DEBUG_MODE = false -- set true to see logs in fs_cli

local function log(level, msg)
  if DEBUG_MODE then freeswitch.consoleLog(level, "[hotel_room_status] " .. msg .. "\n") end
end

-- Set the sound path dynamically
session:setVariable("sound_prefix", "/var/www/fspbx/resources/sounds/en/us/alloy/hotel")

-- Get user extension from session
local extension = session:getVariable("caller_id_number")
local domain_uuid = session:getVariable("domain_uuid")
local extension_uuid = session:getVariable("extension_uuid")

-- Database connection
local Database = require "resources.functions.database";

-- Ensure we have a live call
if not session:ready() then
  freeswitch.consoleLog("ERR", "[hotel_room_status] No active session.\n")
  return
end

session:answer()
session:sleep(1000)

-- Grab call variables set by dialplan
local domain_uuid = session:getVariable("domain_uuid")
local caller_ext  = session:getVariable("caller_id_number") or session:getVariable("sip_from_user") or session:getVariable("effective_caller_id_number")
local code_str    = session:getVariable("room_status") -- set by: <action application="set" data='room_status=$1'/>

log("INFO", string.format("domain_uuid=%s caller_ext=%s code=%s", tostring(domain_uuid), tostring(caller_ext), tostring(code_str)))

-- Basic validation
local code_num = tonumber(code_str or "")
if not code_num or code_num < 0 or code_num > 99 then
  session:streamFile("invalid_code_fmt.wav")
  log("ERR", "Invalid housekeeping code: " .. tostring(code_str))
  session:hangup()
  return
end

if not domain_uuid or not caller_ext then
  log("ERR", "Missing domain_uuid or caller_ext")
  session:hangup()
  return
end

-- DB handle
local dbh = Database.new('system')
if not dbh then
  freeswitch.consoleLog("ERR", "[hotel_room_status] DB connection failed.\n")
  session:streamFile("housekeeping_save_failed.wav")
  session:hangup()
  return
end

-- Helper: fetch a single column from first row
local function fetch_one(sql, params, field)
    local value = nil
    dbh:query(sql, params, function(row)
      value = row[field]
    end)
    return value
  end
  
  -- 1) Resolve extension_uuid from v_extensions
  local extension_uuid = fetch_one(
    [[SELECT extension_uuid
        FROM v_extensions
       WHERE domain_uuid = :domain_uuid
         AND extension = :extension
       LIMIT 1]],
    { domain_uuid = domain_uuid, extension = caller_ext },
    "extension_uuid"
  )
  
  if not extension_uuid then
    log("ERR", "No v_extensions row for extension=" .. tostring(caller_ext))
    session:streamFile("extension_not_found.wav")
    session:hangup()
    return
  end
  
  -- 2) Resolve room_uuid from hotel_rooms
  local room_uuid = fetch_one(
    [[SELECT uuid
        FROM hotel_rooms
       WHERE domain_uuid   = :domain_uuid
         AND extension_uuid = :extension_uuid
       LIMIT 1]],
    { domain_uuid = domain_uuid, extension_uuid = extension_uuid },
    "uuid"
  )
  
  if not room_uuid then
    log("ERR", "No hotel_rooms match for extension_uuid=" .. tostring(extension_uuid))
    session:streamFile("room_not_found.wav")
    session:hangup()
    return
  end
  
  -- 3) Look up housekeeping definition UUID for THIS DOMAIN ONLY (no global fallback)
  local hk_def_uuid = fetch_one(
    [[SELECT uuid
        FROM hotel_housekeeping_definitions
       WHERE enabled = true
         AND domain_uuid = :domain_uuid
         AND code = :code
       LIMIT 1]],
    { domain_uuid = domain_uuid, code = code_num },
    "uuid"
  )
  
  if not hk_def_uuid then
    log("ERR", "Housekeeping code not defined for domain: code=" .. tostring(code_num))
    session:streamFile("code_not_defined.wav")
    session:hangup()
    return
  end
  
  log("INFO", "Resolved housekeeping definition uuid=" .. hk_def_uuid .. " for code=" .. tostring(code_num))
  
  -- 4) Upsert hotel_room_status (write the DEFINITION UUID into housekeeping_status)
  local status_uuid = fetch_one(
    [[SELECT uuid
        FROM hotel_room_status
       WHERE domain_uuid     = :domain_uuid
         AND hotel_room_uuid = :room_uuid
       LIMIT 1]],
    { domain_uuid = domain_uuid, room_uuid = room_uuid },
    "uuid"
  )
  
  dbh:query("BEGIN", {})
  
  local ok = true
  if status_uuid then
    log("INFO", "Updating status uuid=" .. status_uuid .. " housekeeping_status=" .. hk_def_uuid)
    ok = dbh:query(
      [[UPDATE hotel_room_status
           SET housekeeping_status = :hk_uuid,
               updated_at = NOW()
         WHERE uuid = :uuid]],
      { hk_uuid = hk_def_uuid, uuid = status_uuid }
    )
  else
    log("INFO", "Inserting status for room_uuid=" .. room_uuid .. " housekeeping_status=" .. hk_def_uuid)
    ok = dbh:query(
      [[INSERT INTO hotel_room_status
          (domain_uuid, hotel_room_uuid, housekeeping_status, created_at, updated_at)
        VALUES
          (:domain_uuid, :room_uuid, :hk_uuid, NOW(), NOW())]],
      { domain_uuid = domain_uuid, room_uuid = room_uuid, hk_uuid = hk_def_uuid }
    )
  end

  -- Build optional maid code (if you have one; set to nil or a var you collect elsewhere)
    local maid_code = session:getVariable("maid_code") or ""

    -- Queue a pending PMS action: smdr_type = 'S' (Room Status)
    local ok = dbh:query(
        [[INSERT INTO hotel_pending_actions
            (uuid, domain_uuid, hotel_room_uuid, smdr_type, data, created_at, updated_at)
            VALUES
            (uuid_generate_v4(), :domain_uuid, :room_uuid, 'S',
            jsonb_build_object('room_status', :room_status, 'maid', :maid)::jsonb,
            NOW(), NOW())
        ]],
        {
            domain_uuid = domain_uuid,
            room_uuid   = room_uuid,
            room_status = tostring(code_num),  -- "2" per spec (string)
            maid        = maid_code            -- nil if not present
        }
    )
  
  if ok then
    dbh:query("COMMIT", {})
    session:streamFile("housekeeping_save_success.wav")
    log("INFO", "Housekeeping updated to def_uuid=" .. hk_def_uuid .. " for room_uuid=" .. room_uuid)
  else
    dbh:query("ROLLBACK", {})
    session:streamFile("housekeeping_save_failed.wav")
    log("ERR", "DB error while saving housekeeping status")
  end
  
  session:sleep(150)
  session:hangup()