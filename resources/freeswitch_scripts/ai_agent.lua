-- Route a tenant AI Agent extension directly to Retell over the external profile.

local DEBUG_MODE = false

local function log(message)
    if DEBUG_MODE then
        freeswitch.consoleLog("NOTICE", "[ai_agent.lua] " .. tostring(message) .. "\n")
    end
end

local function blank(value)
    return value == nil or tostring(value) == "" or tostring(value) == "_undef_"
end

local function truthy(value)
    value = tostring(value or ""):lower()
    return value == "true" or value == "t" or value == "1"
end

local function trim(value)
    return tostring(value or ""):match("^%s*(.-)%s*$")
end

local function resolve_global_value(value)
    value = trim(value)
    local variable_name = value:match("^%$%${([%w_.%-]+)}$")

    if not variable_name then
        return value
    end

    local api = freeswitch.API()
    return trim(api:execute("global_getvar", variable_name))
end

local agent_uuid = tostring(argv[1] or ""):lower()
local domain_uuid = session:getVariable("domain_uuid")
local domain_name = session:getVariable("domain_name")

if not agent_uuid:match("^%x%x%x%x%x%x%x%x%-%x%x%x%x%-%x%x%x%x%-%x%x%x%x%-%x%x%x%x%x%x%x%x%x%x%x%x$") then
    freeswitch.consoleLog("WARNING", "[ai_agent.lua] Refusing an invalid AI Agent UUID.\n")
    return
end

if blank(domain_uuid) and blank(domain_name) then
    freeswitch.consoleLog("WARNING", "[ai_agent.lua] The call has no account context.\n")
    return
end

local ok, agent = pcall(function()
    local Database = require "resources.functions.database"
    local dbh = Database.new("system")
    assert(dbh:connected())

    local row = dbh:first_row([[
        select
            a.ai_agent_uuid,
            a.recording_policy,
            a.enabled,
            a.provisioning_status,
            i.public_sip_host,
            s.sip_profile_setting_value as external_sip_port
        from ai_agents a
        join v_domains d on d.domain_uuid = a.domain_uuid
        join ai_provider_integrations i
          on i.provider = a.provider
         and i.enabled = true
        join v_sip_profiles p
          on lower(p.sip_profile_name) = 'external'
         and p.sip_profile_enabled = 'true'
        join v_sip_profile_settings s
          on s.sip_profile_uuid = p.sip_profile_uuid
         and s.sip_profile_setting_name = 'sip-port'
         and s.sip_profile_setting_enabled = 'true'
        where a.ai_agent_uuid = :agent_uuid
          and (
                a.domain_uuid = :domain_uuid
                or (:domain_uuid = '' and d.domain_name = :domain_name)
              )
        limit 1
    ]], {
        agent_uuid = agent_uuid,
        domain_uuid = blank(domain_uuid) and "" or domain_uuid,
        domain_name = blank(domain_name) and "" or domain_name,
    })

    dbh:release()
    return row
end)

if not ok then
    freeswitch.consoleLog("ERR", "[ai_agent.lua] The AI Agent lookup failed; the call will not be routed.\n")
    return
end

if not agent or not truthy(agent.enabled) or agent.provisioning_status ~= "synced" then
    freeswitch.consoleLog("WARNING", "[ai_agent.lua] No enabled synchronized AI Agent was found for this account.\n")
    return
end

local public_sip_host = tostring(agent.public_sip_host or "")
local external_sip_port = resolve_global_value(agent.external_sip_port)

if not public_sip_host:match("^[A-Za-z0-9][A-Za-z0-9%.%-]*$")
    or not external_sip_port:match("^%d+$")
    or tonumber(external_sip_port) < 1
    or tonumber(external_sip_port) > 65535 then
    freeswitch.consoleLog("WARNING", "[ai_agent.lua] The AI provider has no valid public SIP destination.\n")
    return
end

local public_sip_destination = public_sip_host .. ":" .. external_sip_port

if agent.recording_policy == "always" and not truthy(session:getVariable("record_in_progress")) then
    if blank(session:getVariable("record_ext")) then
        session:setVariable("record_ext", "wav")
    end
    session:execute("set", "record_path=${recordings_dir}/${domain_name}/archive/${strftime(%Y)}/${strftime(%b)}/${strftime(%d)}")
    session:execute("set", "record_name=${uuid}.${record_ext}")
    session:execute("set", "record_append=true")
    session:execute("set", "record_in_progress=true")
    session:execute("set", "recording_follow_transfer=true")
    session:execute("record_session", "${record_path}/${record_name}")
end

session:setVariable("sip_h_X-FSPBX-Agent-UUID", agent_uuid)
session:setVariable("sip_h_X-FSPBX-SIP-Host", public_sip_destination)
local dial_string = "sofia/external/" .. agent_uuid .. "@sip.retellai.com;transport=tcp"
log("Routing a validated AI Agent call to Retell.")
log("Final dial string: " .. dial_string)
session:execute("bridge", dial_string)
