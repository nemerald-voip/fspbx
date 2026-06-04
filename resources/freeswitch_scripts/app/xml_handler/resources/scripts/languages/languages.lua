--	xml_handler.lua
--	Part of FusionPBX
--	Copyright (C) 2013-2018 Mark J Crane <markjcrane@fusionpbx.com>
--	All rights reserved.
--
--	Redistribution and use in source and binary forms, with or without
--	modification, are permitted provided that the following conditions are met:
--
--	1. Redistributions of source code must retain the above copyright notice,
--	   this list of conditions and the following disclaimer.
--
--	2. Redistributions in binary form must reproduce the above copyright
--	   notice, this list of conditions and the following disclaimer in the
--	   documentation and/or other materials provided with the distribution.
--
--	THIS SOFTWARE IS PROVIDED ''AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
--	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
--	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
--	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
--	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
--	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
--	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
--	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
--	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
--	POSSIBILITY OF SUCH DAMAGE.
--
--	Contributor(s):
--	Mark J Crane <markjcrane@fusionpbx.com>

--additional information
	--event_calling_function = params:getHeader("Event-Calling-Function");

--show the params in the console
	--if (params:serialize() ~= nil) then
	--	freeswitch.consoleLog("notice", "[xml_handler-languages.lua] Params:\n" .. params:serialize() .. "\n");
	--end

--get the action
	--action = params:getHeader("action");
	language = params:getHeader("lang");
	macro_name = params:getHeader("macro_name");

--get the cache
	local cache = require "resources.functions.cache"
	local language_cache_key = "languages:" .. language..":" .. macro_name;
	XML_STRING, err = cache.get(language_cache_key)

-- Database-backed phrases are no longer part of new installs.
	if not XML_STRING then
		--send to the console
			if (debug["cache"]) then
				freeswitch.consoleLog("notice", "[xml_handler] " .. language_cache_key .. " source: not found\n");
			end
	else
		--send to the console
			if (debug["cache"]) then
				freeswitch.consoleLog("notice", "[xml_handler] " .. language_cache_key .. " source: cache\n");
			end
	end --if XML_STRING

--send the xml to the console
	if (debug["xml_string"] and XML_STRING ~= nil) then
		local file = assert(io.open(temp_dir .. "/languages.conf.xml", "w"));
		file:write(XML_STRING);
		file:close();
	end

--if the macro does not exist send "not found", don't cache the not found
	if (XML_STRING == nil or trim(XML_STRING) == "-ERR NOT FOUND") then
		XML_STRING = [[<?xml version="1.0" encoding="UTF-8" standalone="no"?>
		<document type="freeswitch/xml">
			<section name="result">
				<result status="not found" />
			</section>
		</document>]];
	end

--send the xml to the console
	if (debug["xml_string"]) then
		freeswitch.consoleLog("notice", "[xml_handler] XML_STRING: \n" .. XML_STRING .. "\n");
	end
