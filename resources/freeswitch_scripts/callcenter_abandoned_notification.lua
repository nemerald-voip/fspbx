-- Send a queued email notification when a mod_callcenter caller leaves
-- before an agent answers.
-- Arguments: caller session UUID, queue name, and queue UUID.

-- Enable/Disable debug mode globally
DEBUG_MODE = false  -- Set to false to disable debug logs

-- Debug logging function
function debug_log(level, message)
    if DEBUG_MODE then
        freeswitch.consoleLog(level, message .. "\n")
    end
end

local api = freeswitch.API()
local caller_uuid = argv[1]
local queue_name = argv[2]
local queue_uuid = argv[3]
local CHANNEL_DISAPPEAR_GRACE_SECONDS = 2

local function trim(value)
    return tostring(value or ""):match("^%s*(.-)%s*$")
end

local function valid_uuid(value)
    return type(value) == "string"
        and #value == 36
        and value:match("^%x%x%x%x%x%x%x%x%-%x%x%x%x%-%x%x%x%x%-%x%x%x%x%-%x%x%x%x%x%x%x%x%x%x%x%x$") ~= nil
end

local function valid_queue_name(value)
    return type(value) == "string"
        and value:match("^[%w%._%+%-]+@[%w%._%-]+$") ~= nil
end

local function json_escape(value)
    value = tostring(value or "")
    value = value:gsub("\\", "\\\\")
    value = value:gsub('"', '\\"')
    value = value:gsub("'", "\\u0027")
    value = value:gsub("\b", "\\b")
    value = value:gsub("\f", "\\f")
    value = value:gsub("\n", "\\n")
    value = value:gsub("\r", "\\r")
    value = value:gsub("\t", "\\t")
    return value
end

local function header(event, name)
    return event:getHeader(name) or ""
end

local function is_matching_queue_end(event)
    return header(event, "CC-Action") == "member-queue-end"
        and header(event, "CC-Queue") == queue_name
        and header(event, "CC-Member-Session-UUID") == caller_uuid
end

local function queue_notification(event)
    local payload = string.format(
        '{"event":"send_contact_center_abandoned_call_email","data":{"queue_uuid":"%s","queue_name":"%s","call_uuid":"%s","member_uuid":"%s","caller_id_name":"%s","caller_id_number":"%s","joined_epoch":"%s","leaving_epoch":"%s","cancel_reason":"%s"}}',
        json_escape(queue_uuid),
        json_escape(queue_name),
        json_escape(caller_uuid),
        json_escape(header(event, "CC-Member-UUID")),
        json_escape(header(event, "CC-Member-CID-Name")),
        json_escape(header(event, "CC-Member-CID-Number")),
        json_escape(header(event, "CC-Member-Joined-Time")),
        json_escape(header(event, "CC-Member-Leaving-Time")),
        json_escape(header(event, "CC-Cancel-Reason"))
    )
    local result = trim(api:executeString(
        string.format("luarun lua/send_webhook.lua '%s'", payload)
    ))

    if not result:match("^%+OK") then
        debug_log("ERR", "[callcenter_abandoned_notification.lua] Unable to queue webhook: " .. result)
        return false
    end

    debug_log("INFO", "[callcenter_abandoned_notification.lua] Queued abandoned call notification for " .. queue_name)
    return true
end

if not valid_uuid(caller_uuid) then
    debug_log("ERR", "[callcenter_abandoned_notification.lua] Invalid or missing caller UUID")
    return
end

if not valid_queue_name(queue_name) then
    debug_log("ERR", "[callcenter_abandoned_notification.lua] Invalid or missing queue name")
    return
end

if not valid_uuid(queue_uuid) then
    debug_log("ERR", "[callcenter_abandoned_notification.lua] Invalid or missing queue UUID")
    return
end

local events = freeswitch.EventConsumer("CUSTOM", "callcenter::info")
local channel_missing_since = nil

debug_log("INFO", "[callcenter_abandoned_notification.lua] Waiting for queue outcome for " .. queue_name)

while true do
    local event = events:pop(1, 1000)

    if event and is_matching_queue_end(event) then
        if header(event, "CC-Cause"):lower() == "cancel" then
            if header(event, "CC-Cancel-Reason"):upper() == "EXIT_WITH_KEY" then
                debug_log("INFO", "[callcenter_abandoned_notification.lua] Caller used the queue exit key; notification suppressed")
            else
                queue_notification(event)
            end
        else
            debug_log("INFO", "[callcenter_abandoned_notification.lua] Queue call ended without abandonment")
        end

        return
    end

    local cause = trim(api:executeString("uuid_getvar " .. caller_uuid .. " cc_cause")):lower()
    if cause == "answered" then
        debug_log("INFO", "[callcenter_abandoned_notification.lua] Caller was answered; stopping worker")
        return
    end

    local channel_exists = trim(api:executeString("uuid_exists " .. caller_uuid)) == "true"
    if channel_exists then
        channel_missing_since = nil
    else
        channel_missing_since = channel_missing_since or os.time()

        if os.time() - channel_missing_since >= CHANNEL_DISAPPEAR_GRACE_SECONDS then
            debug_log("INFO", "[callcenter_abandoned_notification.lua] Caller channel disappeared without a matching queue event; stopping worker")
            return
        end
    end
end
