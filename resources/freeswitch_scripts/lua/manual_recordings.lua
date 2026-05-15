-- Enable/Disable debug mode globally
DEBUG_MODE = false; -- Set to true to enable debug logs

-- Debug logging function
function debug_log(level, message)
	if (DEBUG_MODE) then
		freeswitch.consoleLog(level, message .. "\n");
	end
end

-- Manual recordings sound prompt path
local recordings_sound_prefix = "/var/www/fspbx/resources/sounds/en/us/alloy/recordings";

function get_recording_approval_digit(session)
	local digits = session:playAndGetDigits(1, 1, max_tries, 5000, "#", "recording_review_options.wav", "", "[12]");
	return digits;
end

--set the variables
	pin_number = "";
	max_tries = 3;
	digit_timeout = 3000;
	sounds_dir = "";
	recordings_dir = "";
	file_name = "";
	recording_number = "";
	recording_id = "";
	recording_prefix = "";
	recording_description = "";

--include config.lua
	require "resources.functions.config";

--add functions
	require "resources.functions.mkdir";
	require "resources.functions.explode";
	local cache = require "resources.functions.cache"

--setup the database connection
	local Database = require "resources.functions.database";
	local db = dbh or Database.new('system');

--get the domain_uuid
	if (session:ready()) then
		domain_uuid = session:getVariable("domain_uuid");
		user_uuid = session:getVariable("user_uuid");
	end

--initialize the recordings
	api = freeswitch.API();

--clear cached prefix and password, refreshed from database settings
	if cache.support() then
		cache.del("setting::recordings.recording_prefix.text")
		cache.del("setting::recordings.recording_password.numeric")
	end

--load lazy settings library
	local Settings = require "resources.functions.lazy_settings";

--get the recordings settings
	local settings = Settings.new(db, domain_name, domain_uuid, user_uuid);

--set the storage type and path
	storage_type = settings:get('recordings', 'storage_type', 'text') or '';
	storage_path = settings:get('recordings', 'storage_path', 'text') or '';
	if (storage_path ~= '') then
		storage_path = storage_path:gsub("${domain_name}", session:getVariable("domain_name"));
		storage_path = storage_path:gsub("${domain_uuid}", domain_uuid);
	end

--set the recordings variables
	local recording_max_length = settings:get('recordings', 'recording_max_length', 'numeric') or 90;
	local recording_silence_threshold = settings:get('recordings', 'recording_silence_threshold', 'numeric') or 200;
	local recording_silence_seconds = settings:get('recordings', 'recording_silence_seconds', 'numeric') or 3;

--set the temp directory
	temp_dir = settings:get('server', 'temp', 'dir') or nil;

--dtmf call back function detects the "#" and ends the call
	function onInput(s, type, obj)
		if (type == "dtmf" and obj['digit'] == '#') then
			return "break";
		end
	end

--start the recording
	function begin_record(session, sounds_dir, recordings_dir)
		debug_log("INFO", "[manual_recordings.lua] recordings_dir: " .. tostring(recordings_dir));

		--set the sounds path for the language, dialect and voice
			default_language = session:getVariable("default_language");
			default_dialect = session:getVariable("default_dialect");
			default_voice = session:getVariable("default_voice");
			if (not default_language) then default_language = 'en'; end
			if (not default_dialect) then default_dialect = 'us'; end
			if (not default_voice) then default_voice = 'callie'; end
			recording_id = session:getVariable("recording_id");
			recording_prefix = settings:get('recordings', 'recording_prefix', 'text') or session:getVariable("recording_prefix");
			recording_name = session:getVariable("recording_name");
			record_ext = session:getVariable("record_ext");
			domain_name = session:getVariable("domain_name");
			time_limit_secs = session:getVariable("time_limit_secs");
			silence_thresh = session:getVariable("silence_thresh");
			silence_hits = session:getVariable("silence_hits");
			if (not time_limit_secs) then time_limit_secs = '10800'; end
			if (not silence_thresh) then silence_thresh = '200'; end
			if (not silence_hits) then silence_hits = '10'; end

		--select the recording number and set the recording filename
			if (recording_id == nil) then
				min_digits = 1;
				max_digits = 20;
				session:sleep(1000);
--				recording_id = session:playAndGetDigits(min_digits, max_digits, max_tries, digit_timeout, "#", sounds_dir.."/"..default_language.."/"..default_dialect.."/"..default_voice.."/ivr/ivr-id_number.wav", "", "\\d+");
				debug_log("INFO", "[manual_recordings.lua] Prompting for recording ID.");
				recording_id = session:playAndGetDigits(min_digits, max_digits, max_tries, digit_timeout, "#", "recording_id_prompt.wav", "", "\\d+");
				session:setVariable("recording_id", recording_id);
				recording_filename = recording_prefix..recording_id.."."..record_ext;
			elseif (tonumber(recording_id) ~= nil) then
				recording_filename = recording_prefix..recording_id.."."..record_ext;
			else
				recording_filename = recording_prefix.."."..record_ext;
			end
			debug_log("INFO", "[manual_recordings.lua] Recording filename: " .. tostring(recording_filename));

		--set the default recording name if one was not provided
			if (recording_name) then
				--recording name is provided do nothing
			else
				--set a default recording_name
				recording_name = recording_filename;
			end

		--prompt for the recording
--			session:streamFile(sounds_dir.."/"..default_language.."/"..default_dialect.."/"..default_voice.."/ivr/ivr-recording_started.wav");
			debug_log("INFO", "[manual_recordings.lua] Playing recording begin prompt.");
			session:streamFile("recording_begin.wav");
			session:execute("playback","silence_stream://200");
			session:streamFile("tone_stream://L=1;%(1000, 0, 640)");
			session:execute("set", "playback_terminators=#");
			debug_log("INFO", "[manual_recordings.lua] Recording prompt completed; playback terminator set.");

		--make the directory
			mkdir(recordings_dir);

		--begin recording
			if (storage_type == "http_cache") then
				debug_log("INFO", "[manual_recordings.lua] Recording file: " .. tostring(storage_path.."/"..recording_filename));
				session:execute("record", storage_path .."/"..recording_filename);
			else
				-- record,Record File,<path> [<time_limit_secs>] [<silence_thresh>] [<silence_hits>]
				debug_log("INFO", "[manual_recordings.lua] Recording file: " .. tostring(recordings_dir.."/"..recording_filename));
				session:execute("record", "'"..recordings_dir.."/"..recording_filename.."' "..time_limit_secs.." "..silence_thresh.." "..silence_hits);
			end

		--setup the database connection
			local Database = require "resources.functions.database";
			local db = dbh or Database.new('system');

--get the previous recording, if it exists
	sql = "SELECT recording_uuid, recording_description, recording_name ";
	sql = sql .. "FROM v_recordings ";
	sql = sql .. "WHERE domain_uuid = :domain_uuid ";
	sql = sql .. "AND recording_filename = :recording_filename ";
	sql = sql .. "LIMIT 1";

	local lookup_params = {
		domain_uuid = domain_uuid;
		recording_filename = recording_filename;
	};

	local row = db:first_row(sql, lookup_params);
	debug_log("INFO", "[manual_recordings.lua] Existing recording lookup complete. Found existing row: " .. tostring(row ~= nil));

	if (row) then
		--preserve existing description/name unless already provided
		recording_uuid = row.recording_uuid;

		if (recording_description == nil or recording_description == '') then
			recording_description = row.recording_description;
		end

		if (recording_name == nil or recording_name == '') then
			recording_name = row.recording_name;
		end

		--update the existing recording instead of deleting it
		debug_log("INFO", "[manual_recordings.lua] Updating existing recording UUID: " .. tostring(recording_uuid));
		sql = [[
			UPDATE v_recordings
			SET
				recording_description = :recording_description,
				recording_name = :recording_name,
				insert_date = CURRENT_TIMESTAMP
			WHERE domain_uuid = :domain_uuid
				AND recording_filename = :recording_filename
		]];
	else
		--new recording
		recording_uuid = api:execute("create_uuid");
		debug_log("INFO", "[manual_recordings.lua] Creating new recording UUID: " .. tostring(recording_uuid));

		sql = [[
			INSERT INTO v_recordings (
				recording_uuid,
				domain_uuid,
				recording_filename,
				recording_description,
				recording_name,
				insert_date
			)
			VALUES (
				:recording_uuid,
				:domain_uuid,
				:recording_filename,
				:recording_description,
				:recording_name,
				CURRENT_TIMESTAMP
			)
		]];
	end

	local params = {
		recording_uuid = recording_uuid;
		domain_uuid = domain_uuid;
		recording_filename = recording_filename;
		recording_name = recording_name;
		recording_description = recording_description;
	};

	local Database = require "resources.functions.database";
	local db = dbh or Database.new('system');
	db:query(sql, params);

	--preview the recording
		debug_log("INFO", "[manual_recordings.lua] Previewing recording: " .. tostring(recordings_dir.."/"..recording_filename));
		session:streamFile(recordings_dir.."/"..recording_filename);

	--approve the recording, to save the recording press 1 to re-record press 2
		digits = get_recording_approval_digit(session);

		if (digits == "1") then
			--recording saved, hangup
			debug_log("INFO", "[manual_recordings.lua] Recording approved and saved.");
			session:streamFile("recording_saved_bye.wav");
			return;
		elseif (digits == "2") then
			--reset the digit timeout
				digit_timeout = "3000";
			--delete the old recording
				os.remove (recordings_dir.."/"..recording_filename);
				debug_log("INFO", "[manual_recordings.lua] Recording rejected; deleted file and restarting: " .. tostring(recordings_dir.."/"..recording_filename));
				--session:execute("system", "rm "..);
			--make a new recording
				begin_record(session, sounds_dir, recordings_dir);
		else
			--recording saved, hangup
				debug_log("INFO", "[manual_recordings.lua] No approval digit selected; keeping recording.");
				session:streamFile("recording_saved_bye.wav");
			return;
		end
	end

if (session:ready()) then
	session:answer();

	--get the dialplan variables and set them as local variables
		pin_number = settings:get('recordings', 'recording_password', 'numeric') or session:getVariable("pin_number");
		sounds_dir = session:getVariable("sounds_dir");
		domain_name = session:getVariable("domain_name");
		domain_uuid = session:getVariable("domain_uuid");

	--add the domain name to the recordings directory
		recordings_dir = recordings_dir .. "/"..domain_name;

	--if a recording directory is specified, use that instead
		if (storage_path ~= nil and string.len(storage_path) > 0) then
			recordings_dir = storage_path;
		end

		--set the sounds path for the language, dialect and voice
			default_language = session:getVariable("default_language");
			default_dialect = session:getVariable("default_dialect");
			default_voice = session:getVariable("default_voice");
			if (not default_language) then default_language = 'en'; end
			if (not default_dialect) then default_dialect = 'us'; end
			if (not default_voice) then default_voice = 'callie'; end

		--use the manual recordings prompt set for the rest of the workflow
			session:setVariable("sound_prefix", recordings_sound_prefix);

		--if the pin number is provided then require it
			if (pin_number) then
				debug_log("INFO", "[manual_recordings.lua] PIN required.");
				min_digits = string.len(pin_number);
				max_digits = string.len(pin_number)+1;
				digits = session:playAndGetDigits(min_digits, max_digits, max_tries, digit_timeout, "#", "recording_pin_prompt.wav", "", "\\d+");
				if (digits == pin_number) then
					--pin is correct
					debug_log("INFO", "[manual_recordings.lua] PIN accepted.");
				else
					debug_log("INFO", "[manual_recordings.lua] PIN rejected.");
					session:streamFile("recording_pin_invalid.wav");
					session:hangup("NORMAL_CLEARING");
					return;
				end
			end

		--start recording
		debug_log("INFO", "[manual_recordings.lua] Starting recording workflow.");
		begin_record(session, sounds_dir, recordings_dir);

	--hangup the call
		session:hangup();

end
