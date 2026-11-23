--include xml library
	local Xml = require "resources.functions.xml";

--get the cache
	local cache = require "resources.functions.cache"
	local local_stream_cache_key = "configuration:local_stream.conf"
	XML_STRING, err = cache.get(local_stream_cache_key)

--set the cache
	if not XML_STRING then

		--log the cache error
			if (debug["cache"]) then
				freeswitch.consoleLog("warning", "[xml_handler] configuration:local_stream.conf can not be get from cache: " .. tostring(err) .. "\n");
			end

		--set a default value
			if (expire["default"] == nil) then
				expire["default"]= "3600";
			end

		--connect to the database
			local Database = require "resources.functions.database";
			dbh = Database.new('system');

		--include json library
			local json
			if (debug["sql"]) then
				json = require "resources.functions.lunajson"
			end

		--exits the script if we didn't connect properly
			assert(dbh:connected());

		--start the xml array
			local xml = Xml:new();
			xml:append([[<?xml version="1.0" encoding="UTF-8" standalone="no"?>]]);
			xml:append([[<document type="freeswitch/xml">]]);
			xml:append([[	<section name="configuration">]]);
			xml:append([[		<configuration name="local_stream.conf" description="stream files from local dir">]]);

		--run the query
			sql = "select d.domain_name, s.* "
			sql = sql .. "from v_music_on_hold as s left outer join v_domains as d "
			sql = sql .. "on d.domain_uuid = s.domain_uuid "
			sql = sql .. "order by d.domain_name asc, s.music_on_hold_name asc, s.music_on_hold_rate asc "
			if (debug["sql"]) then
				freeswitch.consoleLog("notice", "[xml_handler] SQL: " .. sql .. "\n");
			end
			local voice_dir = table.concat({sounds_dir, freeswitch.getGlobalVariable("default_language") or "en",
				freeswitch.getGlobalVariable("default_dialect") or "us", freeswitch.getGlobalVariable("default_voice") or "callie"}, "/")

			local function resolve_chime(path, rate, domain_name)
				local category, filename = path:match("^([^/]+)/([^/]+)$")
				if category then
					local base = voice_dir .. "/" .. category .. "/"
					return file_exists(base .. rate .. "/" .. filename) or file_exists(base .. filename) or path
				end
				-- Older streams stored account recordings as bare filenames.
				if recordings_dir and domain_name and domain_name ~= "" and not path:find("/", 1, true) then
					return file_exists(recordings_dir .. "/" .. domain_name .. "/" .. path) or path
				end
				return path
			end
			dbh:query(sql, function(row)

				--combine the name and domain_name
				name = '';
				if (row.domain_uuid ~= nil and string.len(row.domain_uuid) > 0) then
					name = row.domain_name..'/';
				end
				name = name .. row.music_on_hold_name;

				--replace the variable with the path to the sounds directory
				music_on_hold_path = row.music_on_hold_path:gsub("$${sounds_dir}", sounds_dir);

				--set the rate
				rate = row.music_on_hold_rate;
				if rate == nil or rate == '' then
					rate = '48000';
				end

				--set channels and interval
				channels = row.music_on_hold_channels;
				if channels == nil or channels == '' then
					channels = '1';
				end
				interval = row.music_on_hold_interval;
				if interval == nil or interval == '' then
					interval = '20';
				end

				local chimes = {}
				for entry in (row.music_on_hold_chime_list or ""):gmatch("[^,]+") do
					local path = entry:match("^%s*(.-)%s*$")
					if path ~= "" then
						table.insert(chimes, resolve_chime(path, rate, row.domain_name))
					end
				end

				--set the default timer name to soft
				if (row.music_on_hold_timer_name == nil or row.music_on_hold_timer_name == '') then
					timer_name = "soft";
				else
					timer_name = row.music_on_hold_timer_name;
				end

				--build the xml ]]..row.music_on_hold_name..[["
				xml:append([[	<directory name="]] .. xml.sanitize(name) .. [[" uuid="]] .. xml.sanitize(row.music_on_hold_uuid) .. [[" path="]] .. xml.sanitize(music_on_hold_path) .. [[">]]);
				xml:append([[			<param name="rate" value="]] .. xml.sanitize(rate) .. [["/>]]);
				xml:append([[			<param name="shuffle" value="]] .. xml.sanitize(row.music_on_hold_shuffle) .. [["/>]]);
				xml:append([[			<param name="channels" value="]] .. xml.sanitize(channels) .. [["/>]]);
				xml:append([[			<param name="interval" value="]] .. xml.sanitize(interval) .. [["/>]]);
				xml:append([[			<param name="timer-name" value="]] .. xml.sanitize(timer_name) ..[["/>]]);
				if #chimes > 0 then
					xml:append([[			<param name="chime-list" value="]] .. xml.escape(table.concat(chimes, ",")) .. [["/>]]);
				end
				if (row.music_on_hold_chime_freq ~= nil) then
					xml:append([[			<param name="chime-freq" value="]] .. xml.sanitize(row.music_on_hold_chime_freq) .. [["/>]]);
				end
				if (row.music_on_hold_chime_max ~= nil) then
					xml:append([[			<param name="chime-max" value="]] .. xml.sanitize(row.music_on_hold_chime_max) .. [["/>]]);
				end
				xml:append([[		</directory>]]);

			end)

		--close the extension tag if it was left open
			xml:append([[		</configuration>]]);
			xml:append([[	</section>]]);
			xml:append([[</document>]]);
			XML_STRING = xml:build();
			if (debug["xml_string"]) then
				freeswitch.consoleLog("notice", "[xml_handler] XML_STRING: " .. XML_STRING .. "\n");
			end

		--close the database connection
			dbh:release();

		--set the cache
			local ok, err = cache.set(local_stream_cache_key, XML_STRING, expire["default"]);
			if debug["cache"] then
				if ok then
					freeswitch.consoleLog("notice", "[xml_handler] " .. local_stream_cache_key .. " stored in the cache\n");
				else
					freeswitch.consoleLog("warning", "[xml_handler] " .. local_stream_cache_key .. " can not be stored in the cache: " .. tostring(err) .. "\n");
				end
			end

		--send to the console
			if (debug["cache"]) then
				freeswitch.consoleLog("notice", "[xml_handler] " .. local_stream_cache_key .. " source: database\n");
			end
	else
		--send to the console
			if (debug["cache"]) then
				freeswitch.consoleLog("notice", "[xml_handler] " .. local_stream_cache_key .. " source: cache\n");
			end
	end --if XML_STRING

--send the xml to the console
	if (debug["xml_string"]) then
		local file = assert(io.open(temp_dir .. "/local_stream.conf.xml", "w"));
		file:write(XML_STRING);
		file:close();
	end
