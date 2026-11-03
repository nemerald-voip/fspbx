-- Capture before intercept changes caller profiles. The importer confirms the
-- bridge before using an event; an attempted pickup is never proof of success.
local json = require 'resources.functions.lunajson'
local M = {}

local function uuid(value)
    return type(value) == 'string' and value:match('^%x%x%x%x%x%x%x%x%-%x%x%x%x%-%x%x%x%x%-%x%x%x%x%-%x%x%x%x%x%x%x%x%x%x%x%x$') ~= nil
end

local function value(api, id, name)
    if not uuid(id) then return nil end
    local result = api:executeString('uuid_getvar ' .. id .. ' ' .. name)
    if not result or result == '' or result == '_undef_' or result:sub(1, 4) == '-ERR' then return nil end
    return result
end

local function append(raw, event)
    local events = {}
    if raw then
        local ok, decoded = pcall(json.decode, raw)
        if ok and type(decoded) == 'table' then events = decoded end
    end
    -- Bound channel-variable growth on repeated attempts.
    while #events >= 32 do table.remove(events, 1) end
    events[#events + 1] = event
    return json.encode(events)
end

function M.prepare(session, api, target, ringing, warn)
    local account = session:getVariable('domain_uuid')
    if not uuid(target) or not uuid(account) then
        warn('Pickup target is missing or belongs to another account')
        return false
    end
    local checked, target_account = pcall(value, api, target, 'domain_uuid')
    if not checked then
        warn('Could not read pickup recording/history metadata')
        return true -- Bookkeeping failures must not interrupt existing routing.
    end
    if target_account ~= account then
        warn('Pickup target is missing or belongs to another account')
        return false
    end
    local ok = pcall(function()
        -- Keep this first: history serialization must never prevent CDR creation.
        local result = api:executeString('uuid_setvar ' .. target .. ' force_process_cdr true')
        if not result or result:sub(1, 4) == '-ERR' then error('Cannot preserve pickup CDR') end
        local picker = session:get_uuid()
        local code = session:getVariable('sip_h_X-FSPBX-Pickup-Code') or session:getVariable('destination_number') or ''
        if not (code:match('^%*%*%d+$') or code:match('^%*97%d+$') or code == '*8' or code == '**') then return end
        local peer = value(api, target, 'signal_bond') or value(api, target, 'bridge_uuid')
        ringing = ringing or session:getVariable('sip_h_X-FSPBX-Pickup-Ringing')
            or value(api, peer, 'destination_number') or value(api, target, 'destination_number')
        local event = {
            domain_uuid = account, target_uuid = target, picker_uuid = picker,
            ringing_extension = ringing, pickup_extension = session:getVariable('caller_id_number'),
            -- API:getTime() returns milliseconds; CDR profile times use microseconds.
            code = code, at = tonumber(api:getTime()) * 1000,
            ringing_started = tonumber(value(api, peer, 'start_uepoch')) or tonumber(value(api, target, 'start_uepoch')),
        }
        api:executeString('uuid_setvar ' .. target .. ' fspbx_pickup_events ' .. append(value(api, target, 'fspbx_pickup_events'), event))
        session:setVariable('fspbx_pickup_events', append(session:getVariable('fspbx_pickup_events'), event))
        session:setVariable('force_process_cdr', 'true')
    end)
    if not ok then warn('Could not save pickup recording/history metadata') end
    return true
end

return M
