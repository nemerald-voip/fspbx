-- park_set_callee_id.lua
--
-- Usage:
--   lua park_set_callee_id.lua <parking-lot> <parking-slot>
--
-- When parking:
--   Copies the effective caller ID into the actual caller profile so
--   later connected-party display updates contain the correct name.
--
-- When retrieving:
--   Reads the parked channel identity and places it into the initial
--   answer sent to the phone retrieving the call.

local api = freeswitch.API()

local function clean(value)
    if value == nil then
        return nil
    end

    value = tostring(value)
    value = value:gsub("^%s+", "")
    value = value:gsub("%s+$", "")

    if value == ""
        or value == "_undef_"
        or value:sub(1, 4) == "-ERR"
    then
        return nil
    end

    return value
end

local function uuid_getvar(uuid, variable)
    local command = string.format(
        "uuid_getvar %s %s",
        uuid,
        variable
    )

    return clean(api:executeString(command))
end

if not session or not session:ready() then
    freeswitch.consoleLog(
        "ERR",
        "[park_set_callee_id] No active session\n"
    )
    return
end

local lot = clean(argv and argv[1])
local slot = clean(argv and argv[2])

if not lot or not slot then
    freeswitch.consoleLog(
        "ERR",
        "[park_set_callee_id] Missing parking lot or slot\n"
    )
    return
end

-- Accept the slot with or without a leading asterisk.
slot = slot:gsub("^%*", "")

local valet_info = api:executeString(
    "valet_info " .. lot
) or ""

-- An occupied slot appears in valet_info as:
-- <extension uuid="...">*slot</extension>
local parked_uuid = valet_info:match(
    '<extension%s+uuid="([^"]+)">%*'
        .. slot
        .. '</extension>'
)

if not parked_uuid then
    ----------------------------------------------------------------
    -- EMPTY SLOT: the current call is being placed into park.
    ----------------------------------------------------------------

    -- CNAM lookup and other dialplan processing commonly update the
    -- effective caller-ID variables without changing the underlying
    -- caller profile. FreeSWITCH uses the caller profile when it later
    -- generates connected-party display updates.
    local current_name =
           clean(session:getVariable("effective_caller_id_name"))
        or clean(session:getVariable("caller_id_name"))

    local current_number =
           clean(session:getVariable("effective_caller_id_number"))
        or clean(session:getVariable("caller_id_number"))

if current_name then
    -- Used when the parked channel is treated as the calling party.
    session:execute(
        "set_profile_var",
        "caller_id_name=" .. current_name
    )

    -- Used when the parked channel is treated as the non-originating
    -- side of the new bridge.
    session:execute(
        "set_profile_var",
        "callee_id_name=" .. current_name
    )
end

if current_number then
    session:execute(
        "set_profile_var",
        "caller_id_number=" .. current_number
    )

    session:execute(
        "set_profile_var",
        "callee_id_number=" .. current_number
    )
end

    freeswitch.consoleLog(
        "DEBUG",
        "[park_set_callee_id] Caller profile prepared for parking\n"
    )

    return
end

----------------------------------------------------------------
-- OCCUPIED SLOT: the current call is retrieving a parked call.
----------------------------------------------------------------

local parked_name =
       uuid_getvar(parked_uuid, "parked_caller_id_name")
    or uuid_getvar(parked_uuid, "effective_caller_id_name")
    or uuid_getvar(parked_uuid, "directory_full_name")
    or uuid_getvar(parked_uuid, "caller_id_name")
    or uuid_getvar(parked_uuid, "sip_from_display")
    or uuid_getvar(parked_uuid, "sip_from_user")

local parked_number =
       uuid_getvar(parked_uuid, "parked_caller_id_number")
    or uuid_getvar(parked_uuid, "effective_caller_id_number")
    or uuid_getvar(parked_uuid, "caller_id_number")
    or uuid_getvar(parked_uuid, "sip_from_user")

-- Remove the legacy parking marker if an older parked call contains it.
if parked_name then
    parked_name = parked_name:gsub("^park#", "")
end

if parked_name then
    session:setVariable(
        "initial_callee_id_name",
        parked_name
    )

    session:setVariable(
        "effective_callee_id_name",
        parked_name
    )

    session:setVariable(
        "sip_callee_id_name",
        parked_name
    )
end

if parked_number then
    session:setVariable(
        "initial_callee_id_number",
        parked_number
    )

    session:setVariable(
        "effective_callee_id_number",
        parked_number
    )

    session:setVariable(
        "sip_callee_id_number",
        parked_number
    )
end

session:setVariable(
    "parked_channel_uuid",
    parked_uuid
)

freeswitch.consoleLog(
    "INFO",
    "[park_set_callee_id] Connected-party identity applied\n"
)