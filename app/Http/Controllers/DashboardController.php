<?php

namespace App\Http\Controllers;

use App\Models\CDR;
use Linfo\Linfo;
use Inertia\Inertia;
use App\Models\Faxes;
use App\Models\Extensions;
use App\Models\Destinations;
use Illuminate\Support\Facades\Session;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

class DashboardController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        date_default_timezone_set('America/Los_Angeles');

        // dd(Session::get('domain_name'));
        // return view('layouts.dashboard.index')->with($data);

        return Inertia::render(
            'Dashboard',
            [
                'company_data' => function () {
                    return $this->getCompanyData();
                },
                'cards' => function () {
                    return $this->getApps();
                },
                'data' => Inertia::lazy(
                    fn () =>
                    $this->getData()
                ),
            ]
        );
    }

    public function getData()
    {
        $domain_id = Session::get('domain_uuid');

        $data = [];

        //Extension count
        $data['extensions'] = Extensions::where('domain_uuid', $domain_id)
            ->where('enabled', 'true')
            ->count();

        //Phone Number count
        $data['phone_numbers'] = Destinations::where('domain_uuid', $domain_id)
            ->where('destination_enabled', 'true')
            ->count();

        // Faxes count
        $data['faxes'] = Faxes::where('domain_uuid', $domain_id)
            ->count();

        //CDR Count
        $data['cdrs'] = CDR::where('domain_uuid', $domain_id)
            ->whereRaw("start_stamp >= '" . date('Y-m-d') . " 00:00:00.00 " . get_domain_setting('time_zone') . "'")
            ->count();

        // Get the current status of Horizon.
        if (!$masters = app(MasterSupervisorRepository::class)->all()) {
            $data['horizonStatus'] = 'inactive';
        }

        if (!isset($data['horizonStatus'])) {
            $data['horizonStatus'] =  collect($masters)->every(function ($master) {
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
            $data['global_reg_count'] = count($unique_regs);

            // Count local unique registrations
            $registrations = get_registrations();
            $unique_regs = [];
            foreach ($registrations as $registration) {
                if (!in_array($registration['user'], $unique_regs)) array_push($unique_regs, $registration['user']);
            }
            $data['local_reg_count'] = count($unique_regs);

            //Get Disk Usage
            $data['diskfree'] = disk_free_space(".") / 1073741824;
            $data['disktotal'] = disk_total_space("/") / 1073741824;
            $data['diskused'] = $data['disktotal'] - $data['diskfree'];
            $diskusage = round($data['diskused'] / $data['disktotal'] * 100);
            $data['diskusage'] = $diskusage;

            if ($diskusage <= 60) {
                $data['diskusagecolor'] = "bg-success";
            } elseif ($diskusage > 60 && $diskusage <= 75) {
                $data['diskusagecolor'] = "bg-warning";
            } elseif ($diskusage > 75) {
                $data['diskusagecolor'] = "bg-danger";
            }

            // Get RAM usage
            $linfo = new Linfo;
            $parser = $linfo->getParser();
            $parser->determineCPUPercentage();
            $ram = $parser->getRam();
            $data['ramfree'] = $ram['free'] / 1073741824;
            $data['ramtotal'] = $ram['total'] / 1073741824;
            $data['ramused'] = $data['ramtotal'] - $data['ramfree'];
            $data['ramusage'] = round($data['ramused'] / $data['ramtotal'] * 100);
            if ($data['ramusage'] <= 60) {
                $data['ramusagecolor'] = "bg-success";
            } elseif ($data['ramusage'] > 60 && $data['ramusage'] <= 75) {
                $data['ramusagecolor'] = "bg-warning";
            } elseif ($data['ramusage'] > 75) {
                $data['ramusagecolor'] = "bg-danger";
            }
            $data['swapfree'] = $ram['swapFree'] / 1073741824;
            $data['swaptotal'] = $ram['swapTotal'] / 1073741824;
            $data['swapused'] = $data['swaptotal'] - $data['swapfree'];
            $data['swapusage'] = round($data['swapused'] / $data['swaptotal'] * 100);
            if ($data['swapusage'] <= 60) {
                $data['swapusagecolor'] = "bg-success";
            } elseif ($data['swapusage'] > 60 && $data['swapusage'] <= 75) {
                $data['swapusagecolor'] = "bg-warning";
            } elseif ($data['swapusage'] > 75) {
                $data['swapusagecolor'] = "bg-danger";
            }

            //Get CPU load
            $data['cpuload'] = $parser->getLoad();

            //Get domain total count
            $data['domain_count'] = Session::get("domains")->count();
            // Get extension total count
            $data['extension_count'] = Extensions::get()->count();

            //Get core count
            $data['core_count'] = trim(shell_exec("grep -P '^physical id' /proc/cpuinfo|wc -l"));

            // Get uptime
            $data['uptime'] = $parser->getUpTime();
        }
        return $data;
    }

    public function getCompanyData()
    {
        $data = [];
        if (Session::get('domain_description') != '' && Session::get('domain_description') != null) {
            $data['company_name'] = Session::get('domain_description');
        } else {
            $data['company_name'] = Session::get('domain_name');
        }

        $data['time_zone'] = get_domain_setting('time_zone');

        return $data;
    }

    public function getApps()
    {
        $apps = [];

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



        if (userCheckPermission("user_view")) {
            $apps[] = ['name' => 'Users', 'href' => '/users', 'icon' => 'UsersIcon', 'amount' => '21'];
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
