local enabled = true

ev = freeswitch.Event("PRESENCE_IN")

    ev:addHeader("proto", "sip")

    ev:addHeader("status", "Active (1 waiting)");
	ev:addHeader("rpid", "unknown");
	ev:addHeader("event_count", "1");

    ev:addHeader("event_type", "presence")
    ev:addHeader("alt_event_type", "dialog")

    ev:addHeader("from", "fm100@10001.fspbx.com")
    ev:addHeader("login", "fm100@10001.fspbx.com")

    local uuid = freeswitch.API():execute("create_uuid")
    ev:addHeader("unique-id", uuid)
    ev:addHeader("Presence-Call-Direction", "outbound")

    if enabled then
        ev:addHeader("answer-state", "confirmed")
    else
        ev:addHeader("answer-state", "terminated")
    end

    ev:fire()