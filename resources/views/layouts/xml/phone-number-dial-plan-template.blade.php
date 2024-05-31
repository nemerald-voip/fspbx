<extension name=\"".xml::sanitize($dialplan["dialplan_name"])."\" continue=\"false\" uuid=\"".xml::sanitize($dialplan_uuid)."\">
    <!-- Add dialplan XML destination conditions, if conditions exists -->
    @isset($conditions)
        @foreach($conditions as $row)
            @php
                $condition_expression = (is_numeric($row['condition_expression']) && strlen($destination_number) == strlen($row['condition_expression']) && !empty($destination_prefix))
                ? '\+?' . $destination_prefix . '?' . $row['condition_expression']
                : str_replace("+", "\+", $row['condition_expression']);
            @endphp
            <condition regex="all" break="never">
                <regex field="{{ $dialplan_detail_type }}" expression="{{ xml::sanitize($destination_number_regex)}}"/>
                <regex field="{{ xml::sanitize($row['condition_field']) }}" expression="^{{ xml::sanitize($condition_expression) }}$"/>
                {{-- more actions ... --}}
                {{-- condition_app condition --}}
                @if(isset($row['condition_app']) && !empty($row['condition_app']) && $destination->valid($row['condition_app'].':'.$row['condition_data']))
                    <action application="{{ xml::sanitize($row['condition_app']) }}" data="{{ xml::sanitize($row['condition_data']) }}" />
                @endif
            </condition>
        @endforeach
    @endisset
    <!-- more conditions and actions... -->
    <extension>
    </extension>


    <?php /*
    //add the dialplan xml destination conditions
    if (!empty($conditions)) {
    foreach($conditions as $row) {
    if (is_numeric($row['condition_expression']) && strlen($destination_number) == strlen($row['condition_expression']) && !empty($destination_prefix)) {
    $condition_expression = '\+?'.$destination_prefix.'?'.$row['condition_expression'];
    }
    else {
    $condition_expression = str_replace("+", "\+", $row['condition_expression']);
    }
    $dialplan["dialplan_xml"] .= "	<condition regex=\"all\" break=\"never\">\n";
        $dialplan["dialplan_xml"] .= "		<regex field=\"".$dialplan_detail_type."\" expression=\"".xml::sanitize($destination_number_regex)."\"/>\n";
            $dialplan["dialplan_xml"] .= "		<regex field=\"".xml::sanitize($row['condition_field'])."\" expression=\"^".xml::sanitize($condition_expression)."$\"/>\n";
                $dialplan["dialplan_xml"] .= "		<action application=\"export\" data=\"call_direction=inbound\" inline=\"true\"/>\n";
                    $dialplan["dialplan_xml"] .= "		<action application=\"set\" data=\"domain_uuid=".$_SESSION['domain_uuid']."\" inline=\"true\"/>\n";
                        $dialplan["dialplan_xml"] .= "		<action application=\"set\" data=\"domain_name=".$_SESSION['domain_name']."\" inline=\"true\"/>\n";
                            if (isset($row['condition_app']) && !empty($row['condition_app'])) {
                            if ($destination->valid($row['condition_app'].':'.$row['condition_data'])) {
                            $dialplan["dialplan_xml"] .= "		<action application=\"".xml::sanitize($row['condition_app'])."\" data=\"".xml::sanitize($row['condition_data'])."\"/>\n";
                                }
                                }
                                $dialplan["dialplan_xml"] .= "	</condition>\n";
    }
    }

    $dialplan["dialplan_xml"] .= "	<condition field=\"".$dialplan_detail_type."\" expression=\"".xml::sanitize($destination_number_regex)."\">\n";
        $dialplan["dialplan_xml"] .= "		<action application=\"export\" data=\"call_direction=inbound\" inline=\"true\"/>\n";
            $dialplan["dialplan_xml"] .= "		<action application=\"set\" data=\"domain_uuid=".$_SESSION['domain_uuid']."\" inline=\"true\"/>\n";
                $dialplan["dialplan_xml"] .= "		<action application=\"set\" data=\"domain_name=".$_SESSION['domain_name']."\" inline=\"true\"/>\n";

                    //add this only if using application bridge
                    if (!empty($destination_app) && $destination_app == 'bridge') {
                    $dialplan["dialplan_xml"] .= "		<action application=\"set\" data=\"hangup_after_bridge=true\" inline=\"true\"/>\n";
                        $dialplan["dialplan_xml"] .= "		<action application=\"set\" data=\"continue_on_fail=true\" inline=\"true\"/>\n";
                            }

                            if (!empty($destination_cid_name_prefix)) {
                            $dialplan["dialplan_xml"] .= "		<action application=\"set\" data=\"effective_caller_id_name=".xml::sanitize($destination_cid_name_prefix)."#\${caller_id_name}\" inline=\"false\"/>\n";
                                }
                                if (!empty($destination_record) && $destination_record == 'true') {
                                $dialplan["dialplan_xml"] .= "		<action application=\"set\" data=\"record_path=\${recordings_dir}/\${domain_name}/archive/\${strftime(%Y)}/\${strftime(%b)}/\${strftime(%d)}\" inline=\"true\"/>\n";
                                    $dialplan["dialplan_xml"] .= "		<action application=\"set\" data=\"record_name=\${uuid}.\${record_ext}\" inline=\"true\"/>\n";
                                        $dialplan["dialplan_xml"] .= "		<action application=\"set\" data=\"record_append=true\" inline=\"true\"/>\n";
                                            $dialplan["dialplan_xml"] .= "		<action application=\"set\" data=\"record_in_progress=true\" inline=\"true\"/>\n";
                                                $dialplan["dialplan_xml"] .= "		<action application=\"set\" data=\"recording_follow_transfer=true\" inline=\"true\"/>\n";
                                                    $dialplan["dialplan_xml"] .= "		<action application=\"record_session\" data=\"\${record_path}/\${record_name}\" inline=\"false\"/>\n";
                                                        }
                                                        if (!empty($destination_hold_music)) {
                                                        $dialplan["dialplan_xml"] .= "		<action application=\"export\" data=\"hold_music=".xml::sanitize($destination_hold_music)."\" inline=\"true\"/>\n";
                                                            }
                                                            if (!empty($destination_distinctive_ring)) {
                                                            $dialplan["dialplan_xml"] .= "		<action application=\"export\" data=\"sip_h_Alert-Info=".xml::sanitize($destination_distinctive_ring)."\" inline=\"true\"/>\n";
                                                                }
                                                                if (!empty($destination_accountcode)) {
                                                                $dialplan["dialplan_xml"] .= "		<action application=\"export\" data=\"accountcode=".xml::sanitize($destination_accountcode)."\" inline=\"true\"/>\n";
                                                                    }
                                                                    if (!empty($destination_carrier)) {
                                                                    $dialplan["dialplan_xml"] .= "		<action application=\"set\" data=\"carrier=".xml::sanitize($destination_carrier)."\" inline=\"true\"/>\n";
                                                                        }
                                                                        if (!empty($fax_uuid)) {
                                                                        $dialplan["dialplan_xml"] .= "		<action application=\"set\" data=\"tone_detect_hits=1\" inline=\"true\"/>\n";
                                                                            $dialplan["dialplan_xml"] .= "		<action application=\"set\" data=\"execute_on_tone_detect=transfer ".xml::sanitize($fax_extension)." XML \${domain_name}\" inline=\"true\"/>\n";
                                                                            $dialplan["dialplan_xml"] .= "		<action application=\"tone_detect\" data=\"fax 1100 r +3000\"/>\n";
                                                                            }

                                                                            //add the actions to the dialplan_xml
                                                                            foreach($destination_actions as $destination_action) {
                                                                            $action_array = explode(":", $destination_action, 2);
                                                                            if (isset($action_array[0]) && !empty($action_array[0])) {
                                                                            if ($destination->valid($action_array[0].':'.$action_array[1])) {
                                                                            //set variables from the action array
                                                                            $action_app = $action_array[0];
                                                                            $action_data = $action_array[1];

                                                                            //allow specific api commands
                                                                            $allowed_commands = array();
                                                                            $allowed_commands[] = "regex";
                                                                            $allowed_commands[] = "sofia_contact";
                                                                            foreach ($allowed_commands as $allowed_command) {
                                                                            $action_data = str_replace('${'.$allowed_command, '#{'.$allowed_command, $action_data);
                                                                            }
                                                                            $action_data = xml::sanitize($action_data);
                                                                            foreach ($allowed_commands as $allowed_command) {
                                                                            $action_data = str_replace('#{'.$allowed_command, '${'.$allowed_command, $action_data);
                                                                            }

                                                                            //add the action to the dialplan xml
                                                                            $dialplan["dialplan_xml"] .= "		<action application=\"".xml::sanitize($action_app)."\" data=\"".$action_data."\"/>\n";
                                                                                }
                                                                                }
                                                                                }

                                                                                $dialplan["dialplan_xml"] .= "	</condition>\n";
    $dialplan["dialplan_xml"] .= "</extension>\n";*/
