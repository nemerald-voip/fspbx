-- Resolve an FS PBX bridge UUID at call time.
--
-- Routing features store the bridge UUID instead of copying bridge_destination.
-- This script reads the current bridge immediately before execution, so a
-- bridge header or destination change applies everywhere it is referenced.

DEBUG_MODE = true

local function log(level, message)
    if DEBUG_MODE then
        freeswitch.consoleLog(level, "[bridge.lua] " .. tostring(message) .. "\n")
    end
end

local function blank(value)
    return value == nil or tostring(value) == "" or tostring(value) == "_undef_"
end

local bridge_uuid = tostring(argv[1] or ""):lower()
local domain_uuid = session:getVariable("domain_uuid")
local domain_name = session:getVariable("domain_name")

if not bridge_uuid:match("^%x%x%x%x%x%x%x%x%-%x%x%x%x%-%x%x%x%x%-%x%x%x%x%-%x%x%x%x%x%x%x%x%x%x%x%x$") then
    freeswitch.consoleLog("WARNING", "[bridge.lua] Refusing an invalid bridge UUID.\n")
    return
end

if blank(domain_uuid) and blank(domain_name) then
    freeswitch.consoleLog("WARNING", "[bridge.lua] The call has no account context; the bridge will not run.\n")
    return
end

local headers = {}
local ok, destination = pcall(function()
    local Database = require "resources.functions.database"
    local dbh = Database.new("system")
    assert(dbh:connected())

    local resolved = nil
    local sql = [[
        select
            b.bridge_destination,
            h.header_name,
            h.header_value
        from v_bridges b
        join v_domains d on d.domain_uuid = b.domain_uuid
        left join bridge_headers h
          on h.bridge_uuid = b.bridge_uuid
         and h.domain_uuid = b.domain_uuid
        where b.bridge_uuid = :bridge_uuid
          and b.bridge_enabled = 'true'
          and b.bridge_destination is not null
          and b.bridge_destination <> ''
          and (
                b.domain_uuid = :domain_uuid
                or (:domain_uuid = '' and d.domain_name = :domain_name)
              )
        order by h.sort_order, h.bridge_header_uuid
    ]]
    local params = {
        bridge_uuid = bridge_uuid,
        domain_uuid = blank(domain_uuid) and "" or domain_uuid,
        domain_name = blank(domain_name) and "" or domain_name,
    }

    dbh:query(sql, params, function(row)
        resolved = row.bridge_destination

        local name = tostring(row.header_name or "")
        local value = tostring(row.header_value or "")

        if name:match("^[%w%-]+$") and value ~= "" and not value:match("[,\r\n]") then
            table.insert(headers, "sip_h_" .. name .. "=" .. value)
        end
    end)
    dbh:release()

    return resolved
end)

if not ok then
    freeswitch.consoleLog("ERR", "[bridge.lua] The bridge lookup failed; the call will not be routed.\n")
    return
end

if blank(destination) then
    freeswitch.consoleLog("WARNING", "[bridge.lua] No enabled bridge was found for this account.\n")
    return
end

-- Keep bridge_destination readable and build the FreeSWITCH variable block
-- only when the call is actually routed.
if #headers > 0 then
    local header_variables = table.concat(headers, ",")

    if destination:sub(1, 1) == "{" then
        destination = "{" .. header_variables .. "," .. destination:sub(2)
    else
        destination = "{" .. header_variables .. "}" .. destination
    end
end

log("NOTICE", "Final bridge statement: " .. destination)
session:execute("bridge", destination)
