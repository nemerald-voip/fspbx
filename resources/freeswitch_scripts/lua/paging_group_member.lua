--	paging_group_member.lua
--	Runs on each phone paged by lua/paging_group.lua after it auto-answers.
--	Variables from the originate:
--	  paging_beep        true to play a double beep first
--	  paging_intro       optional announcement file played first
--	  paging_action      conference: join the live page muted
--	                     play: play paging_file, then hang up
--	  paging_conference  the live page conference
--	  paging_file        the recorded page

local BEEP = "tone_stream://L=2;%(150,100,1400)"

if not session:ready() then
	return
end

-- Give the phone a moment to open its speaker so the start is not cut off.
session:sleep(500)

if session:getVariable("paging_beep") == "true" then
	session:streamFile(BEEP)
end

local intro = session:getVariable("paging_intro") or ""
if intro ~= "" and session:ready() then
	session:streamFile(intro)
end

local action = session:getVariable("paging_action")
if action == "conference" then
	local conference = session:getVariable("paging_conference") or ""
	if conference ~= "" and session:ready() then
		session:execute("conference", conference .. "+flags{mute}")
	end
elseif action == "play" then
	local file = session:getVariable("paging_file") or ""
	if file ~= "" and session:ready() then
		session:streamFile(file)
		session:sleep(300)
	end
end

if session:ready() then
	session:hangup("NORMAL_CLEARING")
end
