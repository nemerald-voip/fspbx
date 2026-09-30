-- On-demand queue snapshots through the existing mod_hiredis default profile.
-- One query per queue per five-second window, not per caller.
local M = {}
local ttl = 5
local function trim(value) return tostring(value or ''):match('^%s*(.-)%s*$') end
local function redis(api, command)
    local ok, value = pcall(api.execute, api, 'hiredis_raw', 'default ' .. command)
    if not ok then return nil end
    value = trim(value)
    if value:sub(1, 4) == '-ERR' then return nil end
    return value
end

-- Publish the hash and expiration atomically, only while we own the lock.
-- A compact UUID:rank payload avoids Redis argument limits. Readers need one
-- HGET instead of transferring and decoding the entire queue for each caller.
local publish = [[if redis.call("GET",KEYS[2])~=ARGV[1] then return 0 end
redis.call("DEL",KEYS[1]);redis.call("HSET",KEYS[1],"ready","1")
for id,rank in string.gmatch(ARGV[2],"([%x%-]+):(%d+)") do redis.call("HSET",KEYS[1],id,rank) end
redis.call("EXPIRE",KEYS[1],ARGV[3]);redis.call("DEL",KEYS[2]);return 1]]
publish = publish:gsub('\n', ' ')

local function rows(text)
    local positions, entries, columns, rank = {}, {}, nil, 0
    for line in text:gmatch('[^\r\n]+') do
        local row = {}
        for value in (line .. '|'):gmatch('(.-)|') do row[#row + 1] = value end
        if not columns and row[1] == 'queue' then
            columns = {}
            for i, name in ipairs(row) do columns[name] = i end
        elseif columns and columns.session_uuid and columns.state then
            if row[columns.state] == 'Waiting' or row[columns.state] == 'Trying' then
                rank = rank + 1
                local uuid = row[columns.session_uuid]
                if uuid and uuid:match('^[%x%-]+$') and #uuid == 36 then
                    positions[uuid] = rank
                    entries[#entries + 1] = uuid .. ':' .. rank
                end
            end
        end
    end
    assert(columns and columns.session_uuid and columns.state, 'Invalid queue member response')
    return positions, #entries > 0 and table.concat(entries, ',') or '-'
end

function M.get(api, queue, uuid)
    if not queue:match('^[%w_.+%-]+@[%w_.%-]+$') or not uuid:match('^[%x%-]+$') or #uuid ~= 36 then return nil end
    local core = freeswitch.getGlobalVariable('core_uuid') or ''
    if #core ~= 36 or not core:match('^[%x%-]+$') then return nil end
    -- Positions stay local even if this Redis profile is shared by servers.
    local key = 'cc:positions:' .. core .. ':' .. queue
    local lock = key .. ':lock'
    local function cached()
        local value = redis(api, 'HGET ' .. key .. ' ' .. uuid)
        if value == nil then return nil, true end -- Redis failure: no DB fan-out.
        if tonumber(value) then return tonumber(value), true end
        return nil, redis(api, 'EXISTS ' .. key) ~= '0'
    end
    local position, fresh = cached()
    if fresh then return position end
    local token = trim(api:execute('create_uuid', ''))
    if #token ~= 36 or not token:match('^[%x%-]+$') then return nil end
    if redis(api, 'SET ' .. lock .. ' ' .. token .. ' NX EX 5') ~= 'OK' then return nil end
    -- A different refresher may have published between our read and lock.
    position, fresh = cached()
    if fresh then return position end -- The unused lock expires automatically.
    local ok, positions, payload = pcall(function()
        local response = api:execute('callcenter_config', 'queue list members ' .. queue)
        assert(response and not response:find('-ERR', 1, true), 'Queue member lookup failed')
        return rows(response)
    end)
    -- An empty snapshot also backs off failed queries for five seconds.
    local installed = redis(api, "EVAL '" .. publish .. "' 2 " .. key .. ' ' .. lock .. ' ' .. token .. ' ' .. (ok and payload or '-') .. ' ' .. ttl)
    if installed ~= '1' then return nil end
    return ok and positions[uuid] or nil
end

return M
