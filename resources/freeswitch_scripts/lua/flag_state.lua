-- flag_state.lua
-- Shared module (not a runnable script): decides whether a Flag is active.
-- Used by flag_check.lua, flag_toggle.lua and flag_blf.lua.
--
-- Keep this logic in step with app/Services/FlagStateService.php, which
-- shows the same state on the Flags page.

local M = {}

local api = freeswitch.API()

local function truthy(value)
    local v = tostring(value or ""):lower()
    return v == "t" or v == "true" or v == "1"
end
M.truthy = truthy

-- Current local date/time for a timezone, using FreeSWITCH's own tz database.
-- Returns "YYYY-MM-DD", "HH:MM", wday (1=Sun ... 7=Sat), or nil on failure.
local function local_now(timezone)
    local format = "%Y-%m-%d %H:%M %w"
    local out
    if timezone and timezone ~= "" then
        out = api:execute("strftime_tz", timezone .. " " .. format)
    else
        out = api:execute("strftime", format)
    end

    local date, time, wday = tostring(out or ""):match("^(%d%d%d%d%-%d%d%-%d%d) (%d%d:%d%d) (%d)")
    if not date then
        return nil
    end

    return date, time, tonumber(wday) + 1
end
M.local_now = local_now

local function weekday_set(weekdays)
    local set = {}
    for day in tostring(weekdays or ""):gmatch("%d+") do
        set[tonumber(day)] = true
    end
    return set
end

-- Active from start_time up to (not including) end_time on each selected day.
-- An end time earlier than the start time runs past midnight.
local function weekly_matches(rule, date, time, wday)
    local days = weekday_set(rule.weekdays)
    local s, e = rule.start_time, rule.end_time
    if not s or s == "" or not e or e == "" or s == e then
        return false
    end

    local yesterday = (wday == 1) and 7 or (wday - 1)

    if s < e then
        return days[wday] == true and time >= s and time < e
    end

    return (days[wday] == true and time >= s) or (days[yesterday] == true and time < e)
end

-- Active continuously from start_date start_time (or midnight) through
-- end_date end_time (or the end of that day).
local function date_range_matches(rule, date, time)
    local start_date = rule.start_date
    if not start_date or start_date == "" then
        return false
    end

    local end_date = (rule.end_date and rule.end_date ~= "") and rule.end_date or start_date
    local from = start_date .. " " .. ((rule.start_time and rule.start_time ~= "") and rule.start_time or "00:00")
    local now = date .. " " .. time

    if rule.end_time and rule.end_time ~= "" then
        return now >= from and now < (end_date .. " " .. rule.end_time)
    end

    return now >= from and now <= (end_date .. " 23:59")
end

function M.load_flag_by_uuid(dbh, flag_uuid, domain_uuid)
    return dbh:first_row([[
        SELECT uuid, domain_uuid, flag_number, type, timezone, active, enabled, blf_enabled, pin_number
        FROM flags
        WHERE uuid = :flag_uuid AND domain_uuid = :domain_uuid
        LIMIT 1
    ]], { flag_uuid = flag_uuid, domain_uuid = domain_uuid })
end

function M.load_rules(dbh, flag_uuid)
    local rules = {}
    dbh:query([[
        SELECT rule_type,
               weekdays,
               to_char(start_time, 'HH24:MI') AS start_time,
               to_char(end_time, 'HH24:MI') AS end_time,
               to_char(start_date, 'YYYY-MM-DD') AS start_date,
               to_char(end_date, 'YYYY-MM-DD') AS end_date
        FROM flag_rules
        WHERE flag_uuid = :flag_uuid
    ]], { flag_uuid = flag_uuid }, function(row)
        table.insert(rules, row)
    end)
    return rules
end

-- Returns true/false. Returns nil when the clock could not be read.
function M.rules_active(rules, timezone)
    local date, time, wday = local_now(timezone)
    if not date then
        return nil
    end

    for _, rule in ipairs(rules) do
        if rule.rule_type == "weekly" and weekly_matches(rule, date, time, wday) then
            return true
        end
        if rule.rule_type == "date_range" and date_range_matches(rule, date, time) then
            return true
        end
    end

    return false
end

-- Full decision for a flag row loaded with load_flag_by_uuid.
function M.is_active(dbh, flag)
    if not flag or not truthy(flag.enabled) then
        return false
    end

    if flag.type == "manual" then
        return truthy(flag.active)
    end

    local active = M.rules_active(M.load_rules(dbh, flag.uuid), flag.timezone)
    return active == true
end

return M
