<?php

namespace App\Http\Controllers;

use Auth;
use Linfo\Linfo;
use Carbon\Carbon;
use App\Models\Faxes;
use App\Models\Domain;
use App\Models\Extensions;
use Aws\Mobile\MobileClient;
use Illuminate\Http\Request;
use App\Models\MobileAppUsers;
use App\Models\DefaultSettings;
use App\Jobs\SendAppCredentials;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\AppCredentialsGenerated;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
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
        $domain_id = Session::get('domain_uuid');


        $data = [];
        $data['permissions']['users']=userCheckPermission("user_view");
        $data['permissions']['extensions']=userCheckPermission("extension_view");
        $data['permissions']['ring_groups']=userCheckPermission("ring_group_view");
        $data['permissions']['ivr']=userCheckPermission("ivr_menu_view");
        $data['permissions']['time_conditions']=userCheckPermission("time_condition_view");
        $data['permissions']['devices']=userCheckPermission("device_view");
        $data['permissions']['cdr']=userCheckPermission("xml_cdr_view");
        $data['permissions']['voicemails']=userCheckPermission("voicemail_view");
        $data['permissions']['phone_number']=userCheckPermission("destination_view");
        $data['permissions']['call_flow_view']=userCheckPermission("call_flow_view");
        $data['permissions']['fax_view']=userCheckPermission("fax_view");

        //Users Count
        $data['users'] = DB::table('v_users')->where('domain_uuid', $domain_id)->where('user_enabled', 'true')->count();

        //Extension count
        $data['extensions'] = DB::table('v_extensions')
            ->where('domain_uuid', $domain_id)
            ->where('enabled', 'true')
            ->count();


        //Phone Number count
        $data['phone_number'] = DB::table('v_destinations')
        ->where('domain_uuid', $domain_id)
        ->where('destination_enabled', 'true')
        ->count();

        //Ring group count
        $data['ring_groups'] = DB::table('v_ring_groups')
            ->where('domain_uuid', $domain_id)
            ->where('ring_group_enabled', 'true')->count();;

        //IVR Count
        $data['ivr'] = DB::table('v_ivr_menus')
            ->where('domain_uuid', $domain_id)
            ->where('ivr_menu_enabled', 'true')
            ->count();

        //Time Condition Count
        $data['time_conditions'] = DB::table('v_dialplans')
        ->where('domain_uuid', $domain_id)
        ->where('app_uuid', '4b821450-926b-175a-af93-a03c441818b1')
        ->count();


        //Devices Count
        $data['devices'] = DB::table('v_devices')->where('domain_uuid', $domain_id)->where('device_enabled', 'true')->count();;

        //CDR Count
        $data['cdr'] = DB::table('v_xml_cdr')
            ->where('domain_uuid', $domain_id)
            ->whereRaw("start_stamp >= '".date('Y-m-d')." 00:00:00.00 America/Los_Angeles'")
            ->count();

        //Voicemail Count
        $data['voicemails'] = DB::table('v_voicemails')
            ->where('domain_uuid', $domain_id)
            ->where('voicemail_enabled', 'true')
            ->count();

        //Call Flow Count
        $data['call_flows'] = DB::table('v_call_flows')
            ->where('domain_uuid', $domain_id)
            ->where('call_flow_enabled', 'true')
            ->count();

        //Call Faxes
        $data['faxes'] = Faxes::where('domain_uuid', $domain_id)
        ->count();

        // Get the current status of Horizon.
        if (! $masters = app(MasterSupervisorRepository::class)->all()) {
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
            if(!isset($_SESSION['user'])) {
                return redirect()->route('logout');
            }

            // Count global unique registrations
            $registrations = get_registrations("all");
            $unique_regs =[];
            foreach ($registrations as $registration) {
                if (!in_array($registration['user'], $unique_regs)) array_push($unique_regs,$registration['user']);
            }
            $data['global_reg_count'] = count($unique_regs);

            // Count local unique registrations
            $registrations = get_registrations();
            $unique_regs =[];
            foreach ($registrations as $registration) {
                if (!in_array($registration['user'], $unique_regs)) array_push($unique_regs,$registration['user']);
            }
            $data['local_reg_count'] = count($unique_regs);

            //Get Disk Usage
            $data['diskfree'] = disk_free_space(".") / 1073741824;
            $data['disktotal'] = disk_total_space("/") / 1073741824;
            $data['diskused'] = $data['disktotal'] - $data['diskfree'];
            $diskusage = round($data['diskused']/$data['disktotal']*100);
            $data['diskusage'] = $diskusage;

            if ($diskusage <= 60){
                $data['diskusagecolor'] = "bg-success";
            } elseif ($diskusage > 60 && $diskusage <= 75){
                $data['diskusagecolor'] = "bg-warning";
            } elseif ($diskusage >75) {
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
            $data['ramusage'] = round($data['ramused']/$data['ramtotal']*100);
            if ($data['ramusage'] <= 60){
                $data['ramusagecolor'] = "bg-success";
            } elseif ($data['ramusage'] > 60 && $data['ramusage'] <= 75){
                $data['ramusagecolor'] = "bg-warning";
            } elseif ($data['ramusage'] >75) {
                $data['ramusagecolor'] = "bg-danger";
            }
            $data['swapfree'] = $ram['swapFree'] / 1073741824;
            $data['swaptotal'] = $ram['swapTotal'] / 1073741824;
            $data['swapused'] = $data['swaptotal'] - $data['swapfree'];
            $data['swapusage'] = round($data['swapused']/$data['swaptotal']*100);
            if ($data['swapusage'] <= 60){
                $data['swapusagecolor'] = "bg-success";
            } elseif ($data['swapusage'] > 60 && $data['swapusage'] <= 75){
                $data['swapusagecolor'] = "bg-warning";
            } elseif ($data['swapusage'] >75) {
                $data['swapusagecolor'] = "bg-danger";
            }

            //Get CPU load
            $data['cpuload'] = $parser->getLoad();

            //Get domain total count
            $data['domain_count'] = Session::get("domains")->count();
            // Get extension total count
            $data['extension_count'] = Extensions::get()->count();

            //Get core count
            $data['core_count']=trim(shell_exec("grep -P '^physical id' /proc/cpuinfo|wc -l"));

            // Get uptime
            $data['uptime'] = $parser->getUpTime();

        }

        // dd(Session::get('domain_name'));
        // return view('layouts.dashboard.index')->with($data);

        return Inertia::render(
            'Dashboard',
            [
                // 'data' => function () {
                //     return $data;
                // },
                'cards' => function () {
                    return $this->getApps();
                }
            ]
        );
    }


    public function getApps () {
        $apps = [
            ['name' => 'Ring Groups', 'href' => '#', 'icon' => 'ScaleIcon', 'amount' => '2'],
            ['name' => 'Extensions', 'href' => '#', 'icon' => 'ScaleIcon', 'amount' => '21'],
        ];

        return $apps;
    }

}
