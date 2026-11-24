-- Remove only the channel listing that the operator attempted to hang up.
local api = freeswitch.API()
local db

local function clear()
    local uuid, epoch, encoded_context = argv[1], argv[2], argv[3]
    if not (uuid and #uuid == 36 and uuid:match("^%x+%-%x+%-%x+%-%x+%-%x+$")
        and epoch and epoch:match("^%d+$") and encoded_context and encoded_context:match("^x%x*$")) then
        return false
    end

    local json = require "resources.functions.lunajson"
    local host = api:execute("switchname", ""):match("^%s*(.-)%s*$")
    if host == "" or host:sub(1, 1) == "-" then return false end

    local function listed()
        local result = json.decode(api:execute("show", "channels like " .. uuid .. " as json"))
        local rows = result.rows or {}
        assert(type(result.row_count) == "number" and result.row_count == #rows, "Invalid channel list")
        for _, row in ipairs(rows) do
            if row.uuid == uuid and row.hostname == host then return row end
        end
    end

    local row = listed()
    if not row then return true end -- The switch already removed it.
    local context = row.context or ""
    local hex = context:gsub(".", function(c) return string.format("%02x", c:byte()) end)
    if tostring(row.created_epoch) ~= epoch or encoded_context ~= "x" .. hex then return false end

    local function unreachable()
        return api:execute("uuid_exists", uuid):match("^%s*false%s*$") ~= nil
    end
    if not unreachable() then return false end
    local Database = require "resources.functions.database"
    db = Database.new("switch")
    -- A failed API request is not evidence that the session is inaccessible.
    if not unreachable() then return false end
    local ok = db:query([[
        DELETE FROM channels WHERE hostname = :host AND uuid = :uuid
        AND created_epoch = :epoch AND COALESCE(context, '') = :context
    ]], { host = host, uuid = uuid, epoch = epoch, context = context })
    return ok and not listed()
end

local ok, absent = pcall(clear)
if db then db:release() end
stream:write(ok and absent and "+OK absent" or "-ERR Stale entry could not be cleared")
