<?php

namespace App\Services;

use App\Jobs\SendEventNotify;

class DeviceActionService
{
    public function handleDeviceAction($reg, $action)
    {
        $agent = $this->determineAgent($reg['agent']);
        $command = $this->generateCommand($reg, $action, $agent);

        // Execute command if it is generated
        if (!empty($command)) {
            logger($command);
            SendEventNotify::dispatch($command)->onQueue('default');
        }
    }

    protected function determineAgent($agentString)
    {
        if (preg_match('/Bria|Push|Ringotel/i', $agentString)) {
            return "";
        } elseif (preg_match('/polycom|polyedge/i', $agentString)) {
            return "polycom";
        } elseif (preg_match("/yealink/i", $agentString)) {
            return "yealink";
        } elseif (preg_match("/grandstream/i", $agentString)) {
            return "grandstream";
        }

        return "";
    }

    protected function generateCommand($reg, $action, $vendor)
    {
        switch ($action) {
            case "unregister":
                return "fs_cli -x 'sofia profile " . $reg['sip_profile_name'] . " flush_inbound_reg " . $reg['user'] . " reboot'";
            
            case "provision":
                return "fs_cli -x 'luarun app.lua event_notify " . $reg['sip_profile_name'] . " check_sync " . $reg['user'] . " " . $vendor . "'";

            case "reboot":
                return "fs_cli -x 'luarun app.lua event_notify " . $reg['sip_profile_name'] . " reboot " . $reg['user'] . " " . $vendor . "'";

            default:
                return null; // No valid action, return null
        }
    }
}
