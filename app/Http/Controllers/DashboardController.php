<?php

namespace App\Http\Controllers;

use App\Models\BusinessHour;
use Linfo\Linfo;
use App\Models\User;
use Inertia\Inertia;
use App\Models\Faxes;
use App\Models\Devices;
use App\Models\IvrMenus;
use App\Models\Messages;
use App\Models\CallFlows;
use App\Models\Dialplans;
use App\Models\Extensions;
use App\Models\RingGroups;
use App\Models\Voicemails;
use App\Models\Destinations;
use Illuminate\Support\Carbon;
use App\Models\CallCenterQueues;
use App\Models\WhitelistedNumbers;
use App\Services\CdrDataService;
use Nwidart\Modules\Facades\Module;
use Illuminate\Support\Facades\Session;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use App\Services\FreeswitchEslService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{

    protected $viewName = 'Dashboard';

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        date_default_timezone_set('America/Los_Angeles');

        return Inertia::render(
            $this->viewName,
            [
                'company_data' => function () {
                    return $this->getCompanyData();
                },
                'cards' => function () {
                    return $this->getApps();
                },
                'data' => Inertia::lazy(
                    fn() =>
                    $this->getData()
                ),
                'counts' => Inertia::lazy(
                    fn() =>
                    $this->getCounts()
                ),
                'routes' => [
                    'account_settings_page' => route('account-settings.index'),
                ]
            ]
        );
    }

    public function getCounts()
    {
        $domain_uuid = session('domain_uuid');

        $counts = [];
        if (userCheckPermission("extension_view")) {
            //Extension count
            $counts['extensions'] = Extensions::where('domain_uuid', $domain_uuid)
                ->where('enabled', 'true')
                ->count();
        }

        if (userCheckPermission("user_view")) {
            //User count
            $counts['users'] = User::where('domain_uuid', $domain_uuid)
                ->count();
        }

        //Phone Number count
        $counts['phone_numbers'] = Destinations::where('domain_uuid', $domain_uuid)
            ->where('destination_enabled', 'true')
            ->count();

        // Faxes count
        $counts['faxes'] = Faxes::where('domain_uuid', $domain_uuid)
            ->count();

        //CDR Count
        // $counts['cdrs'] = CDR::where('domain_uuid', $domain_uuid)
        //     ->whereRaw("start_stamp >= '" . date('Y-m-d') . " 00:00:00.00 " . get_domain_setting('time_zone') . "'")
        //     ->count();

        $cdrDataService = new CdrDataService();
        $timezone = get_local_time_zone($domain_uuid);
        $startPeriod = Carbon::now($timezone)->startOfDay()->setTimeZone('UTC')->getTimestamp();
        $endPeriod = Carbon::now($timezone)->endOfDay()->setTimeZone('UTC')->getTimestamp();
        $params['paginate'] = false;
        $params['domain_uuid'] = $domain_uuid;
        $params['filter']['startPeriod'] = $startPeriod;
        $params['filter']['endPeriod'] = $endPeriod;
        // Check if user is allowed to see all CDRs for tenant
        if (!userCheckPermission("xml_cdr_domain")) {
            $user = auth()->user();
            $params['filter']['entity']['value'] = $user->extension_uuid;
            $params['filter']['entity']['type'] = 'extension';
        }
        $cdrs = $cdrDataService->getData($params);

        $counts['cdrs'] = $cdrs->count();

        if (userCheckPermission("ring_group_view")) {
            //Ring group count
            $counts['ring_groups'] = RingGroups::where('domain_uuid', $domain_uuid)
                ->where('ring_group_enabled', 'true')
                ->count();;
        }

        if (userCheckPermission("ivr_menu_view")) {
            //IVR Count
            $counts['ivrs'] = IvrMenus::where('domain_uuid', $domain_uuid)
                ->where('ivr_menu_enabled', 'true')
                ->count();
        }

        if (userCheckPermission("time_condition_view")) {
            //Time Condition Count
            $counts['schedules'] = Dialplans::where('domain_uuid', $domain_uuid)
                ->where('app_uuid', '4b821450-926b-175a-af93-a03c441818b1')
                ->count();
        }

        if (userCheckPermission("device_view")) {
            //Devices Count
            $counts['devices'] = Devices::where('domain_uuid', $domain_uuid)
                ->where('device_enabled', 'true')
                ->count();;
        }

        if (userCheckPermission("voicemail_view")) {
            //Voicemail Count
            $counts['voicemails'] = Voicemails::where('domain_uuid', $domain_uuid)
                ->where('voicemail_enabled', 'true')
                ->count();
        }

        if (userCheckPermission("voicemail_view") && !userCheckPermission("voicemail_domain")) {
            // My VM count
            $user = Auth::user();
            $extension = $user->extension;

            if ($extension) {
                $voicemail = Voicemails::where('domain_uuid', $extension->domain_uuid)
                    ->where('voicemail_id', $extension->extension)
                    ->withCount('messages')
                    ->first();
                $counts['my_voicemails'] = $voicemail?->messages_count ?? 0;
                
            }
        }

        if (userCheckPermission("call_flow_view")) {
            //Call Flow Count
            $counts['call_flows'] = CallFlows::where('domain_uuid', $domain_uuid)
                ->where('call_flow_enabled', 'true')
                ->count();
        }

        if (userCheckPermission("business_hours_list_view")) {
            //Business Hours Count
            $counts['business_hours'] = BusinessHour::where('domain_uuid', $domain_uuid)
                ->where('enabled', 'true')
                ->count();
        }

        //Messages Count
        if (userCheckPermission("message_settings_list_view")) {
            $counts['messages'] = Messages::where('domain_uuid', $domain_uuid)
                ->whereRaw("created_at >= '" . date('Y-m-d') . " 00:00:00.00 " . get_domain_setting('time_zone') . "'")
                ->count();
        }

        //Whitelisted Numbers Count
        if (userCheckPermission("whitelisted_numbers_list_view")) {
            $counts['whitelisted_numbers'] = WhitelistedNumbers::where('domain_uuid', $domain_uuid)
                ->count();
        }

        if (Module::has('ContactCenter') && Module::collections()->has('ContactCenter') && (userCheckPermission("contact_center_settings_edit") || userCheckPermission("contact_center_dashboard_view"))) {
            $counts['queues'] = CallCenterQueues::where('domain_uuid', $domain_uuid)->count();
        }

        $eslService = new FreeswitchEslService();

        //Get all registrations
        $regs = $eslService->getAllSipRegistrations();

        // Get unique extensions online
        $uniqueRegs = $regs->unique('user')->values();
        $counts['global_reg_count'] = $uniqueRegs->count();

        //Filter by domain
        $filteredRegs = $uniqueRegs->where('sip_auth_realm', session('domain_name'))->values();

        $counts['local_reg_count'] = $filteredRegs->count();


        return $counts;
    }

    public function getData()
    {

        $data = [];

        //Check if user is superadmin
        $data['superadmin'] = isSuperAdmin();

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
            // Get Disk Usage
            $totalDiskSpace = disk_total_space("/");
            if ($totalDiskSpace > 0) {
                $data['diskfree'] = disk_free_space(".") / 1073741824;
                $data['disktotal'] = $totalDiskSpace / 1073741824;
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
            } else {
                $data['diskusage'] = 0;
                $data['diskusagecolor'] = "bg-danger";  // Handle total space being zero
            }

            // Get RAM usage
            $linfo = new Linfo;
            $parser = $linfo->getParser();
            $parser->determineCPUPercentage();
            $ram = $parser->getRam();

            if ($ram['total'] > 0) {
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
            } else {
                $data['ramusage'] = 0;
                $data['ramusagecolor'] = "bg-danger";  // Handle total RAM being zero
            }

            if ($ram['swapTotal'] > 0) {
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
            } else {
                $data['swapusage'] = 0;
                $data['swapusagecolor'] = "bg-danger";  // Handle swap total being zero
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
            $uptime = $parser->getUpTime();
            $diff = Carbon::now()->diff(Carbon::createFromTimestamp($uptime['bootedTimestamp']));
            // Format the difference
            $formattedDiff = sprintf('%dd %dh %dm', $diff->days, $diff->h, $diff->i);
            $data['uptime'] = $formattedDiff;

            // Get the hostname of the server
            $data['hostname'] = gethostname();

            $data['version'] = config('app.version');
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
        $data['billing_suspension'] = filter_var(get_domain_setting('billing_suspension'), FILTER_VALIDATE_BOOLEAN);

        return $data;
    }

    public function getApps()
    {
        $apps = [];
        $user = Auth::user();
        $extension = $user->extension;

        $voicemail = null;
        
        if ($extension) {
            $voicemail = Voicemails::where('domain_uuid',$extension->domain_uuid)->where('voicemail_id',$extension->extension)->first();
        }

        if (userCheckPermission("extension_view")) {
            $apps[] = ['name' => 'Extensions', 'href' => route('extensions.index'), 'icon' => 'ContactPhoneIcon', 'slug' => 'extensions'];
        }
        if (userCheckPermission("voicemail_view") && userCheckPermission("voicemail_domain")) {
            $apps[] = ['name' => 'Voicemails', 'href' => route('voicemails.index'), 'icon' => 'VoicemailIcon', 'slug' => 'voicemails'];
        }
        if (userCheckPermission("voicemail_view") && !userCheckPermission("voicemail_domain") && $voicemail) {
            $apps[] = ['name' => 'My VMs', 'href' => $voicemail->messages_route, 'icon' => 'VoicemailIcon', 'slug' => 'my_voicemails'];
        }
        if (userCheckPermission("device_view")) {
            $apps[] = ['name' => 'Devices', 'href' => route('devices.index'), 'icon' => 'DevicesIcon', 'slug' => 'devices'];
        }
        if (userCheckPermission("user_view")) {
            $apps[] = ['name' => 'Users', 'href' => route('users.index'), 'icon' => 'UsersIcon', 'slug' => 'users'];
        }
        if (userCheckPermission("ring_group_view")) {
            $apps[] = ['name' => 'Ring Groups', 'href' => route('ring-groups.index'), 'icon' => 'UserGroupIcon', 'slug' => 'ring_groups'];
        }
        if (userCheckPermission("destination_view")) {
            $apps[] = ['name' => 'Phone Numbers', 'href' => route('phone-numbers.index'), 'icon' => 'DialpadIcon', 'slug' => 'phone_numbers'];
        }
        if (userCheckPermission("ivr_menu_view")) {
            $apps[] = ['name' => 'Virtual Receptionists (IVRs)', 'href' => route('virtual-receptionists.index'), 'icon' => 'IvrIcon', 'slug' => 'ivrs'];
        }
        if (userCheckPermission("business_hours_list_view")) {
            $apps[] = ['name' => 'Business Hours', 'href' => '/business-hours', 'icon' => 'CalendarDaysIcon', 'slug' => 'business_hours'];
        }
        if (userCheckPermission("time_condition_view")) {
            $apps[] = ['name' => 'Schedules', 'href' => '/app/time_conditions/time_conditions.php', 'icon' => 'CalendarDaysIcon', 'slug' => 'schedules'];
        }
        if (userCheckPermission("xml_cdr_view")) {
            $apps[] = ['name' => 'Call History (CDRs)', 'href' => route('cdrs.index'), 'icon' => 'CallHistoryIcon', 'slug' => 'cdrs'];
        }
        if (userCheckPermission("call_flow_view")) {
            $apps[] = ['name' => 'Call Flows', 'href' => '/app/call_flows/call_flows.php', 'icon' => 'AlternativeRouteIcon', 'slug' => 'call_flows'];
        }
        if (userCheckPermission("fax_view")) {
            $apps[] = ['name' => 'Faxes', 'href' => '/faxes', 'icon' => 'FaxIcon', 'slug' => 'faxes'];
        }
        if (userCheckPermission("message_settings_list_view")) {
            $apps[] = ['name' => 'Messages', 'href' => '/messages', 'icon' => 'UsersIcon', 'slug' => 'messages'];
        }
        if (userCheckPermission("whitelisted_numbers_list_view")) {
            $apps[] = ['name' => 'Whitelisted Numbers', 'href' => route('whitelisted-numbers.index'), 'icon' => 'HeartIcon', 'slug' => 'whitelisted_numbers'];
        }

        if (Module::has('ContactCenter') && Module::collections()->has('ContactCenter') && (userCheckPermission("contact_center_settings_edit") || userCheckPermission("contact_center_dashboard_view"))) {

            $queue_count = CallCenterQueues::where('domain_uuid', Session::get('domain_uuid'))->count();

            $contact_center_app = ['name' => 'Contact Center', 'icon' => 'SupportAgent', 'slug' => 'queues'];

            if ($queue_count > 0) {
                if (userCheckPermission("contact_center_dashboard_view")) {
                    $contact_center_app['href'] = '/contact-center';
                }
            }

            if (userCheckPermission("contact_center_settings_edit")) {
                $contact_center_app['alt_href'] = '/contact-center/settings';
                $contact_center_app['alt_link_label'] = 'Settings';
            }

            $apps[] = $contact_center_app;
        }


        return $apps;
    }
}
