-- send BLF presence for vm<extension>@domain
local function vm_blf_notify(account, new_messages, saved_messages)
    local mailbox, domain = string.match(account, "^(.-)@(.-)$")
    if not mailbox or not domain or mailbox == "" or domain == "" then
        return
    end

    local vm_user  = "vm" .. mailbox
    local vm_addr  = vm_user .. "@" .. domain

    new_messages   = tonumber(new_messages)   or 0
    saved_messages = tonumber(saved_messages) or 0

    local has_messages = (new_messages > 0)

    local api  = freeswitch.API()
    local uuid = api:execute("create_uuid")

    local function fire(event_type)
        local ev = freeswitch.Event(event_type)

        ev:addHeader("proto", "sip")
        ev:addHeader("event_type", "presence")
        ev:addHeader("alt_event_type", "dialog")
        ev:addHeader("Presence-Call-Direction", "outbound")

        ev:addHeader("from",  vm_addr)
        ev:addHeader("login", vm_addr)

        ev:addHeader("unique-id", uuid)
        ev:addHeader("status", string.format("Active (%d waiting)", new_messages))
        ev:addHeader("event_count", "1")
        ev:addHeader("rpid", "unknown")

        if has_messages then
            ev:addHeader("answer-state", "confirmed")   -- LED ON
        else
            ev:addHeader("answer-state", "terminated")  -- LED OFF
        end

        freeswitch.consoleLog("NOTICE",
            string.format("[vm_blf] sending %s for %s new=%d saved=%d\n",
                event_type, vm_addr, new_messages, saved_messages))

        ev:fire()
    end

    -- Like flow_notify.lua: send both directions, Poly/Yealink tend to like this
    fire("PRESENCE_OUT")
    fire("PRESENCE_IN")
end

--define a function to send mwi notify
	function mwi_notify(account, new_messages, saved_messages)

		--includes
		require "resources.functions.explode"
		require "resources.functions.trim"

		--create the api object
		api = freeswitch.API();

		local sofia_contacts = trim(api:executeString("sofia_contact */"..account));
		local sofia_contact_table = explode(",", sofia_contacts);

		local sip_profile_table = {};

		for key,value in pairs(sofia_contact_table) do
			f = explode("/", value);
			sip_profile = f[2];


			--check to see if a notify has already been sent to this profile
			new = "true";
			for profile_index, profile_table_value in pairs(sip_profile_table) do
				if profile_table_value == sip_profile then
					new = "false";
				end
			end

			if new == "true" then 
				--debug info
				--freeswitch.consoleLog("NOTICE", "sofia_contact */"..account.."\n");
				--freeswitch.consoleLog("NOTICE", "sip_profile="..sip_profile.."\n");
				--freeswitch.consoleLog("NOTICE", "sofia_contacts="..sofia_contacts.."\n");
		
				--set the variables
				new_messages   = tonumber(new_messages)   or 0
				saved_messages = tonumber(saved_messages) or 0
		
				--set the event and send it
				local event = freeswitch.Event("message_waiting")
				event:addHeader("MWI-Messages-Waiting", (new_messages == 0) and "no" or "yes")
				event:addHeader("MWI-Message-Account", "sip:" .. account)
				event:addHeader("MWI-Voice-Message", string.format("%d/%d (0/0)", new_messages, saved_messages))
				event:addHeader("sofia-profile", sip_profile)
				event:fire()
				
				table.insert(sip_profile_table,sip_profile);
			end
				
		end

			vm_blf_notify(account, new_messages, saved_messages)

	end

--return module value
	return mwi_notify
