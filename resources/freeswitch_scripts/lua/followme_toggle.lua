-- followme_toggle.lua
-- Toggle FollowMe for the current extension and notify BLF subscribers (fm<ext>@domain).

require "resources.functions.config"
require "resources.functions.channel_utils"

local cache  = require "resources.functions.cache"
local Database = require "resources.functions.database"

local api = freeswitch.API()

local function log(level, msg)
    freeswitch.consoleLog(level, "[followme_toggle] " .. msg .. "\n")
end

local function main()
    if not session:ready() then return end

    session:answer()
    session:sleep(1000)
    if not session:ready() then return end

    -- Pull all the important vars from the channel
    local domain_uuid    = session:getVariable("domain_uuid")
    local domain_name    = session:getVariable("domain_name")
    local extension_uuid = session:getVariable("extension_uuid")

    local sounds_dir        = session:getVariable("sounds_dir")
    local default_language  = session:getVariable("default_language") or "en"
    local default_dialect   = session:getVariable("default_dialect") or "us"
    local default_voice     = session:getVariable("default_voice") or "callie"

    if not (domain_uuid and domain_name and extension_uuid) then
        log("ERR", "Missing required session variables (domain_uuid/domain_name/extension_uuid)")
        session:hangup()
        return
    end

    local dbh = Database.new("system")
    if not (dbh and dbh:connected()) then
        log("ERR", "DB connect failed (system)")
        session:hangup()
        return
    end

    -- Get extension info: extension, number_alias, follow_me_uuid, follow_me_enabled
    local sql = [[
        select extension, number_alias, accountcode, follow_me_uuid, follow_me_enabled
        from v_extensions
        where domain_uuid = :domain_uuid
          and extension_uuid = :extension_uuid
    ]]

    local params = {
        domain_uuid    = domain_uuid,
        extension_uuid = extension_uuid,
    }

    local row = dbh:first_row(sql, params)
    if not row then
        log("ERR", "Extension not found for extension_uuid=" .. extension_uuid)
        dbh:release()
        session:hangup()
        return
    end

    local extension        = row.extension
    local number_alias     = row.number_alias or ""
    local follow_me_uuid   = row.follow_me_uuid
    local follow_me_enabled = row.follow_me_enabled or "false"

    local currently_enabled = (tostring(follow_me_enabled):lower() == "true")
    local new_enabled       = not currently_enabled
    local new_enabled_str   = new_enabled and "true" or "false"

    -- User feedback (display + audio)
    local uuid = session:get_uuid()

    if not currently_enabled then
        -- Activating FollowMe
        -- session:execute("sleep", "1000")
        session:streamFile(
            string.format("%s/%s/%s/%s/ivr/ivr-call_forwarding_has_been_set.wav",
                sounds_dir, default_language, default_dialect, default_voice
            )
        )
    else
        -- Deactivating FollowMe
        -- session:execute("sleep", "1000")
        session:streamFile(
            string.format("%s/%s/%s/%s/ivr/ivr-call_forwarding_has_been_cancelled.wav",
                sounds_dir, default_language, default_dialect, default_voice
            )
        )
    end

    -- Toggle v_follow_me.follow_me_enabled (if there is a follow_me_uuid)
    if follow_me_uuid and follow_me_uuid ~= "" then
        local sql_fm = [[
            update v_follow_me
            set follow_me_enabled = :enabled
            where domain_uuid    = :domain_uuid
              and follow_me_uuid = :follow_me_uuid
        ]]

        local p_fm = {
            enabled        = new_enabled_str,
            domain_uuid    = domain_uuid,
            follow_me_uuid = follow_me_uuid,
        }

        dbh:query(sql_fm, p_fm)
    end

    -- Update v_extensions: reset DND and forward_all, toggle follow_me_enabled
    local sql_ext = [[
        update v_extensions
        set do_not_disturb      = 'false',
            follow_me_enabled   = :enabled,
            forward_all_enabled = 'false'
        where domain_uuid    = :domain_uuid
          and extension_uuid = :extension_uuid
    ]]

    local p_ext = {
        enabled        = new_enabled_str,
        domain_uuid    = domain_uuid,
        extension_uuid = extension_uuid,
    }

    dbh:query(sql_ext, p_ext)

    -- Clear directory cache for extension and number_alias
    if extension and cache.support() then
        cache.del("directory:" .. extension .. "@" .. domain_name)
        if #number_alias > 0 then
            cache.del("directory:" .. number_alias .. "@" .. domain_name)
        end
    end

    dbh:release()

    -- Fire BLF presence update (fm<ext>@domain) using your existing helper
    -- This will cause followme_blf.lua / phones subscribed to fm<ext> to update.
    local cmd = string.format(
        "luarun lua/followme_notify.lua %s %s %s",
        extension,
        domain_name,
        new_enabled_str
    )

    api:execute("bgapi", cmd)

    -- small delay then hangup
    session:sleep(1000)
    session:hangup()
end

main()
