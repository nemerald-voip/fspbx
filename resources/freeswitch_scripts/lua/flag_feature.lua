-- Shared server-wide opt-in. Account settings and session variables never enable Flags.
local M = {}

local function truthy(value)
    return value == true or value == 1 or value == "1" or value == "true"
end

function M.enabled(dbh)
    local ok, enabled = pcall(function()
        if not (dbh and dbh:connected()) then return false end
        local rows = {}
        local success = dbh:query([[
            SELECT default_setting_enabled, default_setting_value
            FROM v_default_settings
            WHERE default_setting_category = 'flags'
              AND default_setting_subcategory = 'enabled'
              AND default_setting_name = 'boolean'
        ]], {}, function(row) rows[#rows + 1] = row end)
        return success and #rows == 1
            and truthy(rows[1].default_setting_enabled)
            and truthy(rows[1].default_setting_value)
    end)
    return ok and enabled == true
end

function M.check()
    local dbh
    local ok, enabled = pcall(function()
        dbh = require("resources.functions.database").new("system")
        return M.enabled(dbh)
    end)
    if dbh then pcall(function() dbh:release() end) end
    return ok and enabled == true
end

return M
