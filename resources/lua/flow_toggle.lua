-- flow_toggle.lua
-- FusionPBX-style call flow toggle + BLF (flow*XX)
-- Behavior:
--   *26  → ALWAYS toggle (never route)
--   981  → route based on call_flow_status

require "resources.functions.config"

local log        = require "resources.functions.log".call_flow
local play_file  = require "resources.functions.play_file"
local Database   = require "resources.functions.database"

local api = freeswitch.API()

local max_tries     = 3
local digit_timeout = 5000

-- ---------------------------------------------------------
-- Session variables
-- ---------------------------------------------------------
if not session:ready() then return end

local domain_name       = session:getVariable("domain_name")
local domain_uuid       = session:getVariable("domain_uuid")
local destination_number = session:getVariable("destination_number") or ""

-- Normalize BLF-dialed AoR: flow*26 → *26
local feature_code_input = destination_number
if feature_code_input:sub(1,4) == "flow" then
    -- Ignore "flow+..." entirely
    if feature_code_input:sub(1,5) == "flow+" then
        log.err("Ignoring AoR starting with flow+: " .. feature_code_input)
        session:hangup()
        return
    end
    -- Strip only the "flow" prefix; keep the rest EXACT (could be "*26" or "26")
    feature_code_input = feature_code_input:sub(5)
end

-- ---------------------------------------------------------
-- DB INIT
-- ---------------------------------------------------------
local dbh = Database.new("system")
if not dbh or not dbh:connected() then
    log.err("Database connection failed")
    session:hangup()
    return
end

-- ---------------------------------------------------------
-- Validate PIN
-- ---------------------------------------------------------
local function check_pin(pin)
    if #pin == 0 then return true end

    -- only answer here if we haven't already (feature-code path answers earlier)
    if not session:answered() then
        session:answer()
    end

    local min_digits = #pin
    local max_digits = #pin

    for _ = 1, max_tries do
        local digits = session:playAndGetDigits(
            min_digits, max_digits, max_tries, digit_timeout, "#",
            "phrase:voicemail_enter_pass:#", "", "\\d+"
        )
        if digits == pin then return true end
    end

    session:streamFile("phrase:voicemail_fail_auth:#")
    session:hangup("NORMAL_CLEARING")
    return false
end

-- ---------------------------------------------------------
-- Lookup call flow record by feature code or extension
-- ---------------------------------------------------------
local cf = {}

local function load_call_flow()
    -- Query by exact feature code (as stored)
    local sql = [[
        SELECT *
        FROM v_call_flows
        WHERE (call_flow_feature_code = :fc OR call_flow_extension = :ext)
          AND domain_uuid = :domain_uuid
    ]]

    dbh:query(sql, {
        fc = feature_code_input,
        ext = feature_code_input,
        domain_uuid = domain_uuid
    }, function(row)
        cf = row
    end)

    return cf.call_flow_uuid ~= nil
end

if not load_call_flow() then
    log.err("No call flow match for input: " .. feature_code_input)
    session:hangup()
    return
end

local current_status  = cf.call_flow_status or "true"
local pin_number      = cf.call_flow_pin_number or ""
local feature_code    = cf.call_flow_feature_code or ""
local virtual_ext     = cf.call_flow_extension      -- "981"

if #current_status == 0 then
    current_status = "true"
end

local toggle = (current_status == "true") and "false" or "true"

-- ---------------------------------------------------------
-- PIN check (only for toggles)
-- ---------------------------------------------------------
local is_toggle = (feature_code_input == feature_code)

if is_toggle and #pin_number > 0 then
    if not check_pin(pin_number) then return end
end

-- =========================================================
-- TOGGLE MODE (User dialed *26)
-- =========================================================
if is_toggle then

   if not session:answered() then session:answer() end
    session:sleep(1000)
    ----------------------------------------------------------------
    -- Update DB status
    ----------------------------------------------------------------
    dbh:query(
        "UPDATE v_call_flows SET call_flow_status = :toggle WHERE call_flow_uuid = :uuid",
        { toggle = toggle, uuid = cf.call_flow_uuid }
    )

    ----------------------------------------------------------------
    -- Update BLF: flow*26@domain
    ----------------------------------------------------------------
    local cmd = string.format(
        "luarun lua/flow_notify.lua %s %s %s",
        feature_code, domain_name, toggle
    )
    api:execute("bgapi", cmd)

    ----------------------------------------------------------------
    -- Label Display
    ----------------------------------------------------------------
    local active_label =
        (toggle == "true") and cf.call_flow_label or cf.call_flow_alternate_label

    if active_label and #active_label > 0 then
        session:answer()
        session:sleep(800)
        api:executeString("uuid_display "..session:get_uuid().." "..active_label)
    end

    ----------------------------------------------------------------
    -- Audio Feedback
    ----------------------------------------------------------------
    local audio_file =
        (toggle == "true") and cf.call_flow_sound or cf.call_flow_alternate_sound

    if audio_file and #audio_file > 0 then
        play_file(dbh, domain_name, domain_uuid, audio_file)
    else
        session:streamFile("tone_stream://%(200,0,500,600,700)")
    end

    session:hangup()
    return
end

-- =========================================================
-- NON-TOGGLE MODE (User dialed 981)
-- Route to correct destination
-- =========================================================

local app, data

if current_status == "true" then
    app  = cf.call_flow_app
    data = cf.call_flow_data
else
    app  = cf.call_flow_alternate_app
    data = cf.call_flow_alternate_data
end

log.notice("Executing " .. tostring(app) .. " " .. tostring(data))
session:execute(app, data)
