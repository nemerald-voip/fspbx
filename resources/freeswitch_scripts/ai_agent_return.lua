-- Validate a Retell transfer Request-URI and return it to the owning account.

local DEBUG_MODE = false

local function log(message)
    if DEBUG_MODE then
        freeswitch.consoleLog("NOTICE", "[ai_agent_return.lua] " .. tostring(message) .. "\n")
    end
end

local request_user = tostring(session:getVariable("sip_req_user") or "")
log("Received SIP Request-URI user: " .. request_user)

local agent_uuid, extension = request_user:match(
    "^xfer%.(%x%x%x%x%x%x%x%x%-%x%x%x%x%-%x%x%x%x%-%x%x%x%x%-%x%x%x%x%x%x%x%x%x%x%x%x)%.([%d%*#]+)$"
)

if not agent_uuid or not extension then
    freeswitch.consoleLog("WARNING", "[ai_agent_return.lua] Refusing an invalid Retell transfer target.\n")
    return
end

agent_uuid = agent_uuid:lower()
log("Parsed transfer target: AI Agent UUID=" .. agent_uuid .. ", requested extension=" .. extension)

local ok, target = pcall(function()
    local Database = require "resources.functions.database"
    local dbh = Database.new("system")
    assert(dbh:connected())

    local row = dbh:first_row([[
        select
            a.domain_uuid,
            d.domain_name,
            e.extension
        from ai_agents a
        join v_domains d
          on d.domain_uuid = a.domain_uuid
         and d.domain_enabled = 'true'
        join v_extensions e
          on e.domain_uuid = a.domain_uuid
         and e.extension = :extension
         and e.enabled = 'true'
        where a.ai_agent_uuid = :agent_uuid
          and a.enabled = true
          and a.provisioning_status = 'synced'
        limit 1
    ]], {
        agent_uuid = agent_uuid,
        extension = extension,
    })

    dbh:release()
    return row
end)

if not ok then
    freeswitch.consoleLog("ERR", "[ai_agent_return.lua] The transfer lookup failed; the call will not be routed.\n")
    return
end

if not target or not target.domain_name then
    freeswitch.consoleLog("WARNING", "[ai_agent_return.lua] No valid account extension matched this transfer.\n")
    return
end

log(
    "Matched transfer target: AI Agent UUID=" .. agent_uuid
        .. ", account=" .. tostring(target.domain_name)
        .. ", account UUID=" .. tostring(target.domain_uuid)
        .. ", enabled extension=" .. tostring(target.extension)
)

session:setVariable("domain_uuid", target.domain_uuid)
session:setVariable("domain_name", target.domain_name)
session:setVariable("context", target.domain_name)
session:setVariable("destination_number", target.extension)

local transfer_destination = target.extension .. " XML " .. target.domain_name
log("Final transfer command: " .. transfer_destination)
session:execute("transfer", transfer_destination)
