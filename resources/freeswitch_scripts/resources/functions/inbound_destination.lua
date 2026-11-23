-- Resolve the called number before selecting inbound XML. Keep SIP variables
-- untouched so saved legacy conditions continue to inspect the original call.
local Destination = {}
local Xml = require "resources.functions.xml"
local Json = require "resources.functions.lunajson"

local function public_context_sql(alias)
    return "(" .. alias .. ".dialplan_context = 'public' OR " .. alias
        .. ".dialplan_context LIKE 'public@%' OR " .. alias .. ".dialplan_context LIKE '%.public')"
end

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

local function matches(api, candidate, expression)
    if not expression or expression == "" then return false end
    -- Use the dialplan's PCRE engine, including branch-reset captures and raw
    -- routing patterns. PostgreSQL's regex dialect is not interchangeable.
    for _, delimiter in ipairs({"~", "/", "|"}) do
        if not candidate:find(delimiter, 1, true) and not expression:find(delimiter, 1, true) then
            local result = api:execute("regex", "m:" .. delimiter .. candidate .. delimiter .. expression)
            if result == "true" then return true end
            if result == "false" then return false end
            error("Inbound destination regex evaluation failed")
        end
    end
    return false
end

local function cached_routes(value)
    if not value then return nil end
    local ok, routes = pcall(Json.decode, value)
    if not ok or type(routes) ~= "table" then return nil end
    for key, route in pairs(routes) do
        if type(key) ~= "number" or type(route) ~= "string" then return nil end
    end
    return routes
end

-- Single mode must fetch the routes selected by the expression check. Repeating
-- the literal lookup here would discard national-format matches.
function Destination.selected_routes_sql(routes, params)
    local placeholders = {}
    for index, uuid in ipairs(routes or {}) do
        local name = "inbound_route_" .. index
        placeholders[#placeholders + 1] = ":" .. name
        params[name] = uuid
    end
    if #placeholders == 0 then return "1 = 0" end
    return "p.dialplan_uuid IN (" .. table.concat(placeholders, ", ") .. ")"
end

function Destination.single_dialplan_sql(routes, params)
    return [[SELECT p.dialplan_xml FROM v_dialplans AS p
        LEFT JOIN v_domains AS account ON account.domain_uuid = p.domain_uuid
        WHERE (]] .. Destination.selected_routes_sql(routes, params)
        .. " OR (p.domain_uuid IS NULL AND " .. public_context_sql("p") .. [[))
        AND (p.domain_uuid IS NULL OR account.domain_enabled = 'true')
        AND (p.hostname = :hostname OR p.hostname IS NULL)
        AND p.dialplan_enabled = 'true'
        ORDER BY p.dialplan_order, p.dialplan_uuid]]
end

function Destination.resolve(dbh, cache, api, context, mode, hostname, request_user, to_user, destination_number, ttl)
    local request = request_user and api:execute("url_decode", request_user) or ""
    local recipient = to_user and api:execute("url_decode", to_user) or ""
    -- Preserve non-SIP/diagnostic lookups that provide only a called number.
    if request == "" and recipient == "" then request = destination_number or "" end

    local sql = [[SELECT p.dialplan_uuid, d.destination_number_regex FROM v_destinations AS d
        JOIN v_dialplans AS p ON p.dialplan_uuid = d.dialplan_uuid AND p.domain_uuid = d.domain_uuid
        JOIN v_domains AS account ON account.domain_uuid = p.domain_uuid
        WHERE p.dialplan_enabled = 'true' AND account.domain_enabled = 'true'
        AND COALESCE(d.destination_enabled, 'true') = 'true'
        AND (p.hostname = :hostname OR p.hostname IS NULL)]]
    if mode == "single" then
        sql = sql .. " AND " .. public_context_sql("p")
    else
        sql = sql .. " AND p.dialplan_context = :call_context"
    end
    local ordering = " ORDER BY p.dialplan_order, p.dialplan_uuid"

    -- Reuse the fallback read if both SIP headers need checking. A cold call
    -- performs at most two literal queries and one scoped pattern query.
    local pattern_rows
    local function lookup(candidate)
        local routes, seen = {}, {}
        local params = {destination_number = candidate, hostname = hostname, call_context = context}
        local function collect(row, literal)
            if seen[row.dialplan_uuid] then return end
            local expression = row.destination_number_regex
            if (literal and (not expression or expression == "")) or matches(api, candidate, expression) then
                seen[row.dialplan_uuid] = true
                routes[#routes + 1] = row.dialplan_uuid
            end
        end
        local function query(statement, callback)
            local ok, err = dbh:query(statement, params, callback)
            -- Do not cache partial reads or fall back to another header on DB errors.
            if not ok then error("Inbound destination lookup failed: " .. tostring(err)) end
        end
        if pattern_rows and mode ~= "single" then
            for _, row in ipairs(pattern_rows) do collect(row, false) end
            if #routes > 0 then return routes end
        end
        -- Common carrier formats keep the inexpensive literal lookup. Other
        -- formats and number ranges use exactly the stored dialplan expression.
        query(sql .. " AND " .. Destination.number_match_sql("d") .. ordering, function(row) collect(row, true) end)
        -- Single mode needs every matching route, including overlapping ranges;
        -- multiple mode already loads the whole context after selecting a header.
        if #routes == 0 or mode == "single" then
            if not pattern_rows then
                pattern_rows = {}
                query(sql .. " AND d.destination_number_regex IS NOT NULL AND d.destination_number_regex <> ''" .. ordering,
                    function(row) pattern_rows[#pattern_rows + 1] = row end)
            end
            for _, row in ipairs(pattern_rows) do collect(row, false) end
        end
        return routes
    end

    local seen = {}
    for _, candidate in ipairs({request, recipient}) do
        if candidate ~= "" and #candidate <= 512 and not candidate:find("%c") and not seen[candidate] then
            seen[candidate] = true
            local digest = api:execute("md5", #hostname .. ":" .. hostname .. candidate)
            local key
            if digest and #digest == 32 and digest:match("^%x+$") then
                key = "dialplan:" .. context .. ":selection-v2:" .. mode .. ":" .. digest
            end
            local routes = cached_routes(key and cache.get(key) or nil)
            if not routes then
                routes = lookup(candidate)
                if key then cache.set(key, Json.encode(routes), ttl) end
            end
            if #routes > 0 then return candidate, routes end
        end
    end
    return "", {}
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
