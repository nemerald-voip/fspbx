-- Resolve the called number before selecting inbound XML. Keep SIP variables
-- untouched so saved legacy conditions continue to inspect the original call.
local Destination = {}
local Xml = require "resources.functions.xml"

function Destination.number_match_sql(alias)
    local d = alias .. "."
    return "(" .. table.concat({
        "CONCAT(" .. d .. "destination_prefix, " .. d .. "destination_area_code, " .. d .. "destination_number)",
        "CONCAT(" .. d .. "destination_trunk_prefix, " .. d .. "destination_area_code, " .. d .. "destination_number)",
        "CONCAT(" .. d .. "destination_prefix, " .. d .. "destination_number)",
        "CONCAT('+', " .. d .. "destination_prefix, " .. d .. "destination_number)",
        "CONCAT('+', " .. d .. "destination_prefix, " .. d .. "destination_area_code, " .. d .. "destination_number)",
        "CONCAT(" .. d .. "destination_area_code, " .. d .. "destination_number)",
        d .. "destination_number",
    }, " = :destination_number OR ") .. " = :destination_number)"
end

function Destination.resolve(dbh, cache, api, context, mode, hostname, request_user, to_user, destination_number, ttl)
    local request = request_user and api:execute("url_decode", request_user) or ""
    local recipient = to_user and api:execute("url_decode", to_user) or ""
    -- Preserve non-SIP/diagnostic lookups that provide only a called number.
    if request == "" and recipient == "" then request = destination_number or "" end

    local sql = [[SELECT p.dialplan_uuid FROM v_destinations AS d
        JOIN v_dialplans AS p ON p.dialplan_uuid = d.dialplan_uuid
        JOIN v_domains AS account ON account.domain_uuid = p.domain_uuid
        WHERE p.dialplan_enabled = 'true' AND account.domain_enabled = 'true'
        AND (p.hostname = :hostname OR p.hostname IS NULL) AND ]]
        .. Destination.number_match_sql("d")
    -- Multiple mode only returns this exact context. Single mode preserves
    -- the existing number-based lookup across account-owned inbound routes.
    if mode ~= "single" then sql = sql .. " AND p.dialplan_context = :call_context" end
    sql = sql .. " LIMIT 1"

    local seen = {}
    for _, candidate in ipairs({request, recipient}) do
        if candidate ~= "" and not seen[candidate] then
            seen[candidate] = true
            local digest = api:execute("md5", #hostname .. ":" .. hostname .. candidate)
            local key
            if digest and #digest == 32 and digest:match("^%x+$") then
                key = "dialplan:" .. context .. ":selection-v1:" .. mode .. ":" .. digest
            end
            local matched = key and cache.get(key) or nil
            if matched ~= "true" and matched ~= "false" then
                local route, err = dbh:first_value(sql, {
                    destination_number = candidate, hostname = hostname, call_context = context,
                })
                -- A database failure is not a number mismatch: never cache it
                -- or fall through to a different destination.
                if err then error("Inbound destination lookup failed: " .. tostring(err)) end
                matched = route and "true" or "false"
                if key then cache.set(key, matched, ttl) end
            end
            if matched == "true" then return candidate end
        end
    end
    return ""
end

function Destination.apply(xml, number)
    -- Inject per request, AFTER reading/writing the XML cache. Multiple mode
    -- shares one XML document between calls to different phone numbers.
    local selection = [[<extension name="fspbx-inbound-destination" continue="true">
        <condition><action application="set" data="fspbx_inbound_did=]]
        .. Xml.sanitize(number) .. [[" inline="true"/></condition>
    </extension>]]
    return (xml:gsub("(<context[^>]*>)", function(context)
        return context .. "\n" .. selection
    end, 1))
end

return Destination
