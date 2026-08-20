-- Compact, authenticated call-center agent login/break toggle.
--
-- Dialplan usage:
--   lua lua/agent_toggle.lua login <agent_id>
--   lua lua/agent_toggle.lua break <agent_id>

require "resources.functions.config"

local Database = require "resources.functions.database"

local DEBUG_MODE = true
local SCRIPT_NAME = "[agent_toggle.lua]"
local AGENT_SOUND_PREFIX = "/var/www/fspbx/resources/sounds/en/us/alloy/call_center"
local api = freeswitch.API()

local function log(level, message)
	freeswitch.consoleLog(level, SCRIPT_NAME .. " " .. tostring(message) .. "\n")
end

local function debug_log(message)
	if DEBUG_MODE then
		log("NOTICE", message)
	end
end

local function trim(value)
	return tostring(value or ""):gsub("^%s+", ""):gsub("%s+$", "")
end

local function play_sound(path)
	if session:ready() and path and path ~= "" then
		session:streamFile(path)
	end
end

local function fail(message, sound_path)
	log("WARNING", message)
	play_sound(sound_path)
	if session:ready() then
		session:hangup("NORMAL_CLEARING")
	end
end

local function main()
	if not session or not session:ready() then return end

	local requested_action = tostring(argv[1] or "")
	local requested_agent_id = tostring(argv[2] or "")

	session:answer()
	session:sleep(1000)
	if not session:ready() then return end

	local domain_uuid = session:getVariable("domain_uuid")
	local sip_from_user = session:getVariable("sip_from_user")
	local sounds_dir = session:getVariable("sounds_dir") or "/usr/share/freeswitch/sounds"
	local language = session:getVariable("default_language") or "en"
	local dialect = session:getVariable("default_dialect") or "us"
	local voice = session:getVariable("default_voice") or "callie"
	local sound_root = sounds_dir .. "/" .. language .. "/" .. dialect .. "/" .. voice
	local auth_failure_sound = sound_root .. "/voicemail/vm-fail_auth.wav"

	-- These confirmations are shipped with FS PBX and intentionally use the
	-- Alloy voice regardless of the account's default FreeSWITCH voice.
	session:setVariable("sound_prefix", AGENT_SOUND_PREFIX)

	if requested_action ~= "login" and requested_action ~= "break" then
		fail("Rejected an unsupported compact agent action", auth_failure_sound)
		return
	end

	if not requested_agent_id:match("^%d+$") then
		fail("Rejected a malformed compact agent ID", auth_failure_sound)
		return
	end

-- Log the authentication values used by the self-service authorization
	-- check so mismatches can be diagnosed from the FreeSWITCH log.
	debug_log(string.format(
		"Auth check: domain_uuid='%s', sip_from_user='%s', requested_agent_id='%s', requested_action='%s'",
		tostring(domain_uuid),
		tostring(sip_from_user),
		tostring(requested_agent_id),
		tostring(requested_action)
	))

	-- Compact keys are deliberately self-service only. The authenticated SIP
	-- username must exactly match the requested agent ID.
	if not domain_uuid
		or domain_uuid == ""
		or not sip_from_user
		or tostring(sip_from_user) ~= tostring(requested_agent_id) then

		log("WARNING", string.format(
			"Rejected compact agent action: authentication mismatch. domain_uuid='%s', sip_from_user='%s', requested_agent_id='%s', requested_action='%s'",
			tostring(domain_uuid),
			tostring(sip_from_user),
			tostring(requested_agent_id),
			tostring(requested_action)
		))

		fail("Rejected a compact agent action for a different SIP user", auth_failure_sound)
		return
	end

	local dbh = Database.new("system")
	if not (dbh and dbh:connected()) then
		fail("Unable to connect to the database", auth_failure_sound)
		return
	end

	local agent, lookup_error = dbh:first_row([[
		SELECT call_center_agent_uuid, agent_id, user_uuid
		FROM v_call_center_agents
		WHERE domain_uuid = :domain_uuid
		  AND agent_id = :agent_id
		LIMIT 1
	]], {
		domain_uuid = domain_uuid,
		agent_id = requested_agent_id,
	})

	if lookup_error or not agent or not agent.call_center_agent_uuid then
		dbh:release()
		fail("No matching tenant-scoped call-center agent was found", auth_failure_sound)
		return
	end

	local agent_uuid = tostring(agent.call_center_agent_uuid)
	if not agent_uuid:match("^[0-9a-fA-F%-]+$") then
		dbh:release()
		fail("The call-center agent has an invalid runtime identifier", auth_failure_sound)
		return
	end

	local current_status = trim(api:executeString(
		"callcenter_config agent get status " .. agent_uuid
	))
	local known_status = current_status == "Available"
		or current_status == "Available (On Demand)"
		or current_status == "On Break"
		or current_status == "Logged Out"

	if not known_status then
		dbh:release()
		fail("FreeSWITCH did not return a recognized live agent status", auth_failure_sound)
		return
	end

	local new_status
	local feedback_sound
	if requested_action == "login" then
		local logged_in = current_status == "Available"
			or current_status == "Available (On Demand)"
			or current_status == "On Break"
		if logged_in then
			new_status = "Logged Out"
			feedback_sound = "logged_out.wav"
		else
			new_status = "Available"
			feedback_sound = "logged_in.wav"
		end
	else
		if current_status == "On Break" then
			new_status = "Available"
			feedback_sound = "available.wav"
		else
			new_status = "On Break"
			feedback_sound = "on_break.wav"
		end
	end

	-- Change the live status immediately. agent_blf.lua receives the resulting
	-- mod_callcenter event and publishes both BLF lamps before audio completes.
	local result = trim(api:executeString(
		"callcenter_config agent set status "
			.. agent_uuid
			.. " '"
			.. new_status
			.. "'"
	))
	if result:sub(1, 4) == "-ERR" then
		dbh:release()
		fail("FreeSWITCH rejected the agent status change", auth_failure_sound)
		return
	end

	-- Retain the existing workflow's user status synchronization, but only
	-- after FreeSWITCH accepted the runtime change.
	if agent.user_uuid and tostring(agent.user_uuid) ~= "" then
		dbh:query([[
			UPDATE v_users
			SET user_status = :status
			WHERE user_uuid = :user_uuid
			  AND domain_uuid = :domain_uuid
		]], {
			status = new_status,
			user_uuid = agent.user_uuid,
			domain_uuid = domain_uuid,
		})
	end
	dbh:release()

	debug_log(string.format(
		"Changed agent %s from %s to %s",
		requested_agent_id,
		current_status,
		new_status
	))

	play_sound(feedback_sound)
	if session:ready() then
		api:executeString("uuid_display " .. session:get_uuid() .. " '" .. new_status .. "'")
		session:sleep(2000)
	end
end

main()
