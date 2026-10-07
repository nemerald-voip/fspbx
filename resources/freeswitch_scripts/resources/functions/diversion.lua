-- Keep the inbound business DID independent of changing dialplan destinations.
local M = {}
local DEBUG_MODE = false

local function warn(message)
    freeswitch.consoleLog("WARNING", "[diversion] " .. message .. "\n")
end

local function did(value)
    return type(value) == "string" and value:match("^%+[1-9]%d+$") and #value <= 16
end

local function export(session, name, value)
    -- setVariable avoids evaluating received header contents as dialplan text.
    session:setVariable(name, value)
    local names = session:getVariable("export_vars") or ""
    for existing in names:gmatch("[^,]+") do
        if existing == name then return end
    end
    session:setVariable("export_vars", names == "" and name or names .. "," .. name)
end

local function codec()
    require "resources.functions.base64"
    return base64
end

-- Commas in quoted display names and URI parameters are not entry separators.
local function entries(value)
    if not value or value == "" then return {} end
    if #value > 8192 or value:find("[%c]") or value:find("${", 1, true) then return nil end
    local result, start, quoted, escaped, angle = {}, 1, false, false, 0
    for i = 1, #value do
        local c = value:sub(i, i)
        if escaped then escaped = false
        elseif c == "\\" and quoted then escaped = true
        elseif c == '"' then quoted = not quoted
        elseif not quoted then
            if c == "<" then angle = angle + 1
            elseif c == ">" then angle = angle - 1
            elseif c == "," and angle == 0 then
                result[#result + 1] = value:sub(start, i - 1):match("^%s*(.-)%s*$")
                start = i + 1
            end
            if angle < 0 or angle > 1 then return nil end
        end
    end
    if quoted or escaped or angle ~= 0 then return nil end
    result[#result + 1] = value:sub(start):match("^%s*(.-)%s*$")
    for _, entry in ipairs(result) do
        if not entry:lower():match("<?sips?:[^%s>]+") and not entry:lower():match("<?tel:[^%s>]+") then
            return nil
        end
    end
    return result
end

local function received_chain(session)
    local inherited = ""
    local encoded = session:getVariable("diversion_chain")
    if encoded and encoded ~= "" then
        if #encoded > 11000 or not encoded:match("^[%w+/=]+$") then return nil end
        inherited = codec().decode(encoded)
    else
        -- With parse-all-invite-headers, Sofia exposes every separate received
        -- Diversion line here. sip_h_Diversion alone contains only the last one.
        inherited = session:getVariable("sip_i_diversion") or ""
        if inherited:sub(1, 7) == "ARRAY::" then
            inherited = inherited:sub(8):gsub("|:", ", ")
        end
    end
    local chain, seen = {}, {}
    for _, value in ipairs({ inherited, session:getVariable("sip_h_Diversion") or "" }) do
        local parsed = entries(value)
        if not parsed then return nil end
        local occurrences = {}
        for _, entry in ipairs(parsed) do
            occurrences[entry] = (occurrences[entry] or 0) + 1
            if occurrences[entry] > (seen[entry] or 0) then
                chain[#chain + 1], seen[entry] = entry, occurrences[entry]
            end
        end
    end
    return table.concat(chain, ", ")
end

-- Loopback does not reliably retain the SIP variables from its parent leg.
-- Carry the DID and received chain as data; eligibility is decided at a gateway.
function M.loopback_variables(session)
    local number = session:getVariable("original_did")
    if not did(number) then return "" end
    local vars = "original_did=" .. number .. ","
    local chain = received_chain(session)
    if not chain then
        warn("Received Diversion is malformed; preserving existing behavior.")
        return ""
    end
    if chain ~= "" then
        vars = vars .. "diversion_chain=" .. codec().encode(chain) .. ","
    end
    return vars
end

-- A native gateway endpoint is the boundary. user_exists=false also includes
-- IVRs, other ring groups and queues, so it cannot establish external dialing.
function M.is_gateway(destination)
    local rest = destination or ""
    while rest:sub(1, 1) == "{" or rest:sub(1, 1) == "[" do
        local block = rest:match(rest:sub(1, 1) == "{" and "^%b{}" or "^%b[]")
        if not block then return false end
        -- Leave administrator-supplied inline Diversion overrides untouched.
        if block:lower():find("sip_h_diversion", 1, true) then return false end
        rest = rest:sub(#block + 1)
    end
    return rest:match("^sofia/gateway/[%w_.%-]+/[^,%s|{}%[%]]+$") ~= nil
end

function M.header(session, extra)
    local number = session:getVariable("original_did")
    -- Ordinary outbound calls have no inbound DID and need no local Diversion.
    if not number or number == "" then return nil end
    local host = session:getVariable("domain_name") or ""
    if not did(number) or not host:match("^[%w][%w.%-]*$") then
        warn("Invalid original DID/account domain; no local Diversion added.")
        return nil
    end
    local local_entry = "<sip:" .. number .. "@" .. host .. ">;reason=unconditional;counter=1"
    local chain, seen = { local_entry }, { [local_entry] = true }
    local received = received_chain(session)
    if not received then return nil end
    for source, value in ipairs({ received, extra or "" }) do
        local parsed = entries(value)
        if not parsed then
            warn("Malformed Diversion chain; no local Diversion added.")
            return nil
        end
        for _, entry in ipairs(parsed) do
            if entry ~= local_entry and (source == 1 or not seen[entry]) then
                chain[#chain + 1], seen[entry] = entry, true
            end
        end
    end
    return table.concat(chain, ", ")
end

function M.reset(session)
    local applied = session:getVariable("diversion_applied")
    if not applied then return end
    -- Only undo our own value. An administrator action may have replaced it.
    if session:getVariable("sip_h_Diversion") == applied then
        local previous = session:getVariable("diversion_previous_header")
        session:setVariable("sip_h_Diversion", previous ~= "" and previous or nil)
        if session:getVariable("diversion_added_export") == "true" then
            local names = {}
            for name in (session:getVariable("export_vars") or ""):gmatch("[^,]+") do
                if name ~= "sip_h_Diversion" then names[#names + 1] = name end
            end
            session:setVariable("export_vars", #names > 0 and table.concat(names, ",") or nil)
        end
    end
    session:setVariable("diversion_applied", nil)
    session:setVariable("diversion_previous_header", nil)
    session:setVariable("diversion_added_export", nil)
end

function M.apply(session, extra)
    M.reset(session)
    local header = M.header(session, extra)
    if not header then return false end
    session:setVariable("diversion_previous_header", session:getVariable("sip_h_Diversion") or "")
    local exported = false
    for name in (session:getVariable("export_vars") or ""):gmatch("[^,]+") do
        if name == "sip_h_Diversion" then exported = true end
    end
    session:setVariable("diversion_added_export", exported and "false" or "true")
    session:setVariable("diversion_applied", header)
    -- Raw export avoids interpreting received SIP contents as dialplan text.
    export(session, "sip_h_Diversion", header)
    if DEBUG_MODE then freeswitch.consoleLog("DEBUG", "[diversion] Added local forwarding entry.\n") end
    return true
end

return M
