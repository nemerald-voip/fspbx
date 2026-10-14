--	paging_group.lua
--	Pages a paging group. Called from the paging group's dialplan, which sets:
--	  paging_group_uuid   the group
--	  paging_destinations extension numbers to page, comma separated
--	  paging_mode         live: the caller talks through a muted conference
--	                      recorded: the caller records first, then the recording is played
--	  paging_busy         skip: leave phones already on a call alone; force: page them too
--	  paging_beep         true to play a double beep before the page
--	  paging_intro        optional announcement file played before the page
--	Each paged phone runs lua/paging_group_member.lua.

local DEBUG_MODE = false

local function log(level, message)
	if level ~= "DEBUG" or DEBUG_MODE then
		freeswitch.consoleLog(level, "[paging_group] " .. message .. "\n")
	end
end

local BEEP = "tone_stream://L=2;%(150,100,1400)"
local BUSY_TONE = "tone_stream://%(500,500,480,620);loops=3"
local RECORD_MAX_SECONDS = 120

if not session:ready() then
	return
end

local api = freeswitch.API()
local domain_name = session:getVariable("domain_name") or ""
local group_uuid = session:getVariable("paging_group_uuid") or ""
local destinations = session:getVariable("paging_destinations") or ""
local mode = session:getVariable("paging_mode") or "live"
local busy = session:getVariable("paging_busy") or "skip"
local beep = session:getVariable("paging_beep") == "true"
local intro = session:getVariable("paging_intro") or ""

-- Values placed inside originate {...} must not break its syntax.
local function clean(value)
	return (tostring(value or ""):gsub("[{}',=%c]", ""))
end

-- sip_auth_username is the authenticated caller, so they are never paged themselves.
local caller = session:getVariable("sip_auth_username") or session:getVariable("sip_from_user") or ""
local caller_id_name = clean(session:getVariable("caller_id_name"))
local caller_id_number = clean(session:getVariable("caller_id_number"))

-- Phones that will be paged, leaving out the caller and, when asked, busy phones.
local function page_targets()
	local channels = ""
	if busy == "skip" then
		channels = api:executeString("show channels") or ""
	end

	local targets = {}
	for destination in destinations:gmatch("[^,]+") do
		-- Resolve number aliases the same way page.lua does.
		local user = api:execute("user_data", destination .. "@" .. domain_name .. " attr id")
		if user == nil or user == "" then
			user = destination
		end

		if user == caller then
			log("DEBUG", "skipping the caller " .. user)
		elseif busy == "skip" and channels:find(user .. "@" .. domain_name, 1, true) then
			log("NOTICE", "skipping busy extension " .. user)
		else
			table.insert(targets, user)
		end
	end
	return targets
end

-- Call each phone with auto-answer; the member script plays the intro and the page.
local function page(targets, variables)
	local common = "sip_h_Call-Info=<sip:" .. domain_name .. ">;answer-after=0"
		.. ",sip_h_Alert-Info='Ring Answer'"
		.. ",hangup_after_bridge=false"
		.. ",call_timeout=30"
		.. ",origination_caller_id_name='" .. caller_id_name .. "'"
		.. ",origination_caller_id_number=" .. caller_id_number
		.. ",paging_beep=" .. tostring(beep)
		.. ",paging_intro='" .. clean(intro) .. "'"
		.. "," .. variables

	for _, user in ipairs(targets) do
		api:executeString("bgapi originate {" .. common .. "}user/" .. user .. "@" .. domain_name .. " &lua(lua/paging_group_member.lua)")
	end
	log("NOTICE", "paging " .. #targets .. " phone(s) for group " .. group_uuid .. " in " .. mode .. " mode")
end

session:answer()
session:sleep(300)

if mode == "recorded" then
	local recordings_dir = session:getVariable("recordings_dir") or "/var/lib/freeswitch/recordings"
	local directory = recordings_dir .. "/" .. domain_name .. "/paging"
	local file = directory .. "/page-" .. session:getVariable("uuid") .. ".wav"
	os.execute("mkdir -p '" .. directory:gsub("'", "") .. "'")
	-- Recorded pages are only needed until every phone has played them.
	os.execute("find '" .. directory:gsub("'", "") .. "' -name 'page-*.wav' -mmin +60 -delete")

	-- Record until the caller presses # or hangs up.
	session:setVariable("playback_terminators", "#")
	session:streamFile("tone_stream://%(250,0,800)")
	session:execute("record", file .. " " .. RECORD_MAX_SECONDS .. " 200 5")

	local handle = io.open(file, "rb")
	local size = handle and handle:seek("end") or 0
	if handle then handle:close() end
	-- A WAV header alone is 44 bytes; ignore recordings shorter than about a tenth of a second.
	if size < 2000 then
		log("NOTICE", "no recording to page")
		os.remove(file)
		return
	end

	local targets = page_targets()
	if #targets == 0 then
		log("NOTICE", "no phones available to page")
		if session:ready() then
			session:streamFile(BUSY_TONE)
		end
		return
	end

	page(targets, "paging_action=play,paging_file='" .. clean(file) .. "'")
	if session:ready() then
		session:streamFile(BEEP)
		session:hangup("NORMAL_CLEARING")
	end
	return
end

-- Live: phones join a muted conference that ends when the caller hangs up.
local targets = page_targets()
if #targets == 0 then
	log("NOTICE", "no phones available to page")
	session:streamFile(BUSY_TONE)
	return
end

local conference = "paging-" .. group_uuid .. "-" .. session:getVariable("uuid") .. "@page"
page(targets, "paging_action=conference,paging_conference=" .. conference)

-- Let the phones answer, then play the same intro to the caller so they know when to talk.
session:sleep(1000)
if beep then
	session:streamFile(BEEP)
end
if intro ~= "" then
	session:streamFile(intro)
end

if session:ready() then
	session:execute("conference", conference .. "+flags{endconf,mintwo}")
end
