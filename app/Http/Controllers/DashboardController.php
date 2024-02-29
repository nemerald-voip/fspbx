<?php

namespace App\Http\Controllers;

use Linfo\Linfo;
use App\Models\Extensions;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

class DashboardController extends Controller
{
    public $data;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        date_default_timezone_set('America/Los_Angeles');
        $domain_id = Session::get('domain_uuid');

        $this->data = [];

        if (Session::get('domain_description') != '' && Session::get('domain_description') != null) {
            $this->data['company_name'] = Session::get('domain_description');
        } else {
            $this->data['company_name'] = Session::get('domain_name');
        }

        // Get the current status of Horizon.
        if (!$masters = app(MasterSupervisorRepository::class)->all()) {
            $this->data['horizonStatus'] = 'inactive';
        }

        if (!isset($data['horizonStatus'])) {
            $this->data['horizonStatus'] =  collect($masters)->every(function ($master) {
                return $master->status === 'paused';
            }) ? 'paused' : 'running';
        }

        //if superuser get registration status
        if (isSuperAdmin()) {
            //Check FusionPBX login status
            session_start();
            if (!isset($_SESSION['user'])) {
                return redirect()->route('logout');
            }

            // Count global unique registrations
            $registrations = get_registrations("all");
            $unique_regs = [];
            foreach ($registrations as $registration) {
                if (!in_array($registration['user'], $unique_regs)) array_push($unique_regs, $registration['user']);
            }
            $this->data['global_reg_count'] = count($unique_regs);

            // Count local unique registrations
            $registrations = get_registrations();
            $unique_regs = [];
            foreach ($registrations as $registration) {
                if (!in_array($registration['user'], $unique_regs)) array_push($unique_regs, $registration['user']);
            }
            $this->data['local_reg_count'] = count($unique_regs);

            //Get Disk Usage
            $this->data['diskfree'] = disk_free_space(".") / 1073741824;
            $this->data['disktotal'] = disk_total_space("/") / 1073741824;
            $this->data['diskused'] = $this->data['disktotal'] - $this->data['diskfree'];
            $diskusage = round($this->data['diskused'] / $this->data['disktotal'] * 100);
            $this->data['diskusage'] = $diskusage;

            if ($diskusage <= 60) {
                $this->data['diskusagecolor'] = "bg-success";
            } elseif ($diskusage > 60 && $diskusage <= 75) {
                $this->data['diskusagecolor'] = "bg-warning";
            } elseif ($diskusage > 75) {
                $this->data['diskusagecolor'] = "bg-danger";
            }

            // Get RAM usage
            $linfo = new Linfo;
            $parser = $linfo->getParser();
            $parser->determineCPUPercentage();
            $ram = $parser->getRam();
            $this->data['ramfree'] = $ram['free'] / 1073741824;
            $this->data['ramtotal'] = $ram['total'] / 1073741824;
            $this->data['ramused'] = $this->data['ramtotal'] - $this->data['ramfree'];
            $this->data['ramusage'] = round($this->data['ramused'] / $this->data['ramtotal'] * 100);
            if ($this->data['ramusage'] <= 60) {
                $this->data['ramusagecolor'] = "bg-success";
            } elseif ($this->data['ramusage'] > 60 && $this->data['ramusage'] <= 75) {
                $this->data['ramusagecolor'] = "bg-warning";
            } elseif ($this->data['ramusage'] > 75) {
                $this->data['ramusagecolor'] = "bg-danger";
            }
            $this->data['swapfree'] = $ram['swapFree'] / 1073741824;
            $this->data['swaptotal'] = $ram['swapTotal'] / 1073741824;
            $this->data['swapused'] = $this->data['swaptotal'] - $this->data['swapfree'];
            $this->data['swapusage'] = round($this->data['swapused'] / $this->data['swaptotal'] * 100);
            if ($this->data['swapusage'] <= 60) {
                $this->data['swapusagecolor'] = "bg-success";
            } elseif ($this->data['swapusage'] > 60 && $this->data['swapusage'] <= 75) {
                $this->data['swapusagecolor'] = "bg-warning";
            } elseif ($this->data['swapusage'] > 75) {
                $this->data['swapusagecolor'] = "bg-danger";
            }

            //Get CPU load
            $this->data['cpuload'] = $parser->getLoad();

            //Get domain total count
            $this->data['domain_count'] = Session::get("domains")->count();
            // Get extension total count
            $this->data['extension_count'] = Extensions::get()->count();

            //Get core count
            $this->data['core_count'] = trim(shell_exec("grep -P '^physical id' /proc/cpuinfo|wc -l"));

            // Get uptime
            $this->data['uptime'] = $parser->getUpTime();
        }

        // dd(Session::get('domain_name'));
        // return view('layouts.dashboard.index')->with($data);

        return Inertia::render(
            'Dashboard',
            [
                'data' => function () {
                    return $this->data;
                },
                'cards' => function () {
                    return $this->getApps();
                }
            ]
        );
    }


    public function getApps()
    {
        $apps = [];

        // //Extension count
        // $data['extensions'] = DB::table('v_extensions')
        //     ->where('domain_uuid', $domain_id)
        //     ->where('enabled', 'true')
        //     ->count();


        // //Phone Number count
        // $data['phone_number'] = DB::table('v_destinations')
        //     ->where('domain_uuid', $domain_id)
        //     ->where('destination_enabled', 'true')
        //     ->count();

        // //Ring group count
        // $data['ring_groups'] = DB::table('v_ring_groups')
        //     ->where('domain_uuid', $domain_id)
        //     ->where('ring_group_enabled', 'true')->count();;

        // //IVR Count
        // $data['ivr'] = DB::table('v_ivr_menus')
        //     ->where('domain_uuid', $domain_id)
        //     ->where('ivr_menu_enabled', 'true')
        //     ->count();

        // //Time Condition Count
        // $data['time_conditions'] = DB::table('v_dialplans')
        //     ->where('domain_uuid', $domain_id)
        //     ->where('app_uuid', '4b821450-926b-175a-af93-a03c441818b1')
        //     ->count();


        // //Devices Count
        // $data['devices'] = DB::table('v_devices')->where('domain_uuid', $domain_id)->where('device_enabled', 'true')->count();;

        // //CDR Count
        // $data['cdr'] = DB::table('v_xml_cdr')
        //     ->where('domain_uuid', $domain_id)
        //     ->whereRaw("start_stamp >= '" . date('Y-m-d') . " 00:00:00.00 America/Los_Angeles'")
        //     ->count();

        // //Voicemail Count
        // $data['voicemails'] = DB::table('v_voicemails')
        //     ->where('domain_uuid', $domain_id)
        //     ->where('voicemail_enabled', 'true')
        //     ->count();

        // //Call Flow Count
        // $data['call_flows'] = DB::table('v_call_flows')
        //     ->where('domain_uuid', $domain_id)
        //     ->where('call_flow_enabled', 'true')
        //     ->count();

        // //Call Faxes
        // $data['faxes'] = Faxes::where('domain_uuid', $domain_id)
        //     ->count();


        if (userCheckPermission("user_view")) {
            // //Users Count
            // $count = DB::table('v_users')->where('domain_uuid', $domain_id)->where('user_enabled', 'true')->count();

            $apps[] = [
                'name' => 'Users', 'href' => '/users', 'icon' => 'UsersIcon', 'amount' => '21',
                'iconForeground' => 'text-teal-700', 'iconBackground' => 'bg-teal-50'
            ];
        }

        if (userCheckPermission("extension_view")) {
            $apps[] = ['name' => 'Extensions', 'href' => '/extensions', 'icon' => 'ContactPhoneIcon', 'amount' => '21'];
        }
        if (userCheckPermission("ring_group_view")) {
            $apps[] = ['name' => 'Ring Groups', 'href' => '/ring-groups', 'icon' => 'UserGroupIcon', 'amount' => '2'];
        }
        if (userCheckPermission("ivr_menu_view")) {
            $apps[] = ['name' => 'Virtual Receptionists (IVRs)', 'href' => '/app/ivr_menus/ivr_menus.php', 'icon' => 'IvrIcon', 'amount' => '5'];
        }
        if (userCheckPermission("time_condition_view")) {
            $apps[] = ['name' => 'Schedules', 'href' => '/app/time_conditions/time_conditions.php', 'icon' => 'CalendarDaysIcon', 'amount' => '10'];
        }
        if (userCheckPermission("device_view")) {
            $apps[] = ['name' => 'Devices', 'href' => '/app/devices/devices.php', 'icon' => 'DevicesIcon', 'amount' => '15'];
        }
        if (userCheckPermission("xml_cdr_view")) {
            $apps[] = ['name' => 'Call History (CDRs)', 'href' => '/call-detail-records', 'icon' => 'CallHistoryIcon', 'amount' => '30'];
        }
        if (userCheckPermission("voicemail_view")) {
            $apps[] = ['name' => 'Voicemails', 'href' => '/voicemails', 'icon' => 'VoicemailIcon', 'amount' => '25'];
        }
        if (userCheckPermission("destination_view")) {
            $apps[] = ['name' => 'Phone Numbers', 'href' => '/app/destinations/destinations.php', 'icon' => 'DialpadIcon', 'amount' => '50'];
        }
        if (userCheckPermission("call_flow_view")) {
            $apps[] = ['name' => 'Call Flows', 'href' => '/app/call_flows/call_flows.php', 'icon' => 'AlternativeRouteIcon', 'amount' => '12'];
        }
        if (userCheckPermission("fax_view")) {
            $apps[] = ['name' => 'Faxes', 'href' => '/faxes', 'icon' => 'FaxIcon', 'amount' => '8'];
        }

        return $apps;
    }
}
