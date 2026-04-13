-- fax_hangup.lua
-- Thin fax hangup hook: collect fax vars and send to webhook sender

DEBUG_MODE = false

local function log(level, message)
    if DEBUG_MODE then
        freeswitch.consoleLog(level, "[fax_hangup.lua] " .. tostring(message) .. "\n")
    end
end

require "resources.functions.config"
local json = require "resources.functions.lunajson"

local api = freeswitch.API()

local function header(name, fallback)
    local value = env:getHeader(name)
    if (value == nil or value == "") and fallback then
        value = env:getHeader(fallback)
    end
    if value == nil then
        value = ""
    end
    return tostring(value)
end


local function clean(value)
    value = tostring(value or "")
    value = value:gsub("'", "\\u0027")
    return value
end

local uuid = clean(header("uuid"))
local domain_uuid = clean(header("domain_uuid"))
local domain_name = clean(header("domain_name"))
local call_direction = clean(header("call_direction", "direction"))

local fax_uuid = clean(header("fax_uuid"))
local fax_queue_uuid = clean(header("fax_queue_uuid"))
local fax_success = clean(header("fax_success"))
local fax_result_code = clean(header("fax_result_code"))
local fax_result_text = clean(header("fax_result_text"))
local fax_ecm_used = clean(header("fax_ecm_used"))
local fax_local_station_id = clean(header("fax_local_station_id"))
local fax_remote_station_id = clean(header("fax_remote_station_id"))
local fax_document_transferred_pages = clean(header("fax_document_transferred_pages"))
local fax_document_total_pages = clean(header("fax_document_total_pages"))
local fax_image_resolution = clean(header("fax_image_resolution"))
local fax_image_size = clean(header("fax_image_size"))
local fax_bad_rows = clean(header("fax_bad_rows"))
local fax_transfer_rate = clean(header("fax_transfer_rate"))
local fax_uri = clean(header("fax_uri"))
local fax_extension_number = clean(header("fax_extension_number"))
local fax_file = clean(header("fax_file", "fax_filename"))

local caller_id_name = clean(header("caller_id_name", "Caller-Caller-ID-Name"))
local caller_id_number = clean(header("caller_id_number", "Caller-Caller-ID-Number"))
local sip_to_user = clean(header("sip_to_user"))
local bridge_hangup_cause = clean(header("bridge_hangup_cause"))
local hangup_cause = clean(header("hangup_cause"))
local hangup_cause_q850 = clean(header("hangup_cause_q850"))

local start_epoch = clean(header("start_epoch"))
local answer_epoch = clean(header("answer_epoch"))
local end_epoch = clean(header("end_epoch"))
local duration = clean(header("duration"))
local billsec = clean(header("billsec"))

if fax_success == "" then
    fax_success = "0"
end

if fax_result_text == "" then
    fax_result_text = "FS_NOT_SET"
end

local event_name = "fax.completed"
if call_direction == "inbound" then
    event_name = "fax.received"
elseif call_direction == "outbound" then
    event_name = "fax.sent"
end

local payload_table = {
    event = event_name,
    timestamp = tostring(os.time()),
    data = {
        uuid = uuid,
        domain_uuid = domain_uuid,
        domain_name = domain_name,
        call_direction = call_direction,

        fax_uuid = fax_uuid,
        fax_queue_uuid = fax_queue_uuid,
        fax_success = fax_success,
        fax_result_code = fax_result_code,
        fax_result_text = fax_result_text,
        fax_ecm_used = fax_ecm_used,
        fax_local_station_id = fax_local_station_id,
        fax_remote_station_id = fax_remote_station_id,
        fax_document_transferred_pages = fax_document_transferred_pages,
        fax_document_total_pages = fax_document_total_pages,
        fax_image_resolution = fax_image_resolution,
        fax_image_size = fax_image_size,
        fax_bad_rows = fax_bad_rows,
        fax_transfer_rate = fax_transfer_rate,
        fax_uri = fax_uri,
        fax_extension_number = fax_extension_number,
        fax_file = fax_file,

        caller_id_name = caller_id_name,
        caller_id_number = caller_id_number,
        sip_to_user = sip_to_user,

        bridge_hangup_cause = bridge_hangup_cause,
        hangup_cause = hangup_cause,
        hangup_cause_q850 = hangup_cause_q850,

        start_epoch = start_epoch,
        answer_epoch = answer_epoch,
        end_epoch = end_epoch,
        duration = duration,
        billsec = billsec
    }
}

local payload = json.encode(payload_table)

log("NOTICE", "Payload: " .. payload)

local cmd = string.format("luarun lua/send_webhook.lua '%s'", payload)
log("NOTICE", "CMD: " .. cmd)

local result = api:executeString(cmd)
log("NOTICE", "Result: " .. tostring(result))
