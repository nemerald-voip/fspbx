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
use App\Models\WakeupCall;
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
                'company_data' => $this->getCompanyData(),
                'cards' => $this->getApps(),
                'my_extension_status' => null,
                'data' => [],
                'counts' => [],
                'routes' => [
                    'account_settings_page' => route('account-settings.index'),
                    'data_route' => route('dashboard.data'),
                    'counts_route' => route('dashboard.counts'),
                    'my_extension_status_route' => route('dashboard.my-extension-status'),
                    'extension_item_options' => route('extensions.item.options'),
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
        if (userCheckPermission("xml_cdr_view") && userCheckPermission("xml_cdr_view_self_records") && !userCheckPermission("xml_cdr_view_all_records")) {
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

        $user = Auth::user();
        $extension = $user->extension;

        if (!userCheckPermission("voicemail_view") && $extension) {
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

        if (!userCheckPermission("extension_view") && $extension) {
            $counts['my_extension'] = 1;
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
        if (userCheckPermission("messages_view")) {
            $counts['messages'] = Messages::where('domain_uuid', $domain_uuid)
                ->whereRaw("created_at >= '" . date('Y-m-d') . " 00:00:00.00 " . get_domain_setting('time_zone') . "'")
                ->count();
        }

        //Whitelisted Numbers Count
        if (userCheckPermission("whitelisted_numbers_list_view")) {
            $counts['whitelisted_numbers'] = WhitelistedNumbers::where('domain_uuid', $domain_uuid)
                ->count();
        }

        //Wakeup Calls Count
        if (userCheckPermission("wakeup_calls_list_view")) {
            $counts['wakeup_calls'] = WakeupCall::where('domain_uuid', $domain_uuid)
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

    public function getMyExtensionStatus(): ?array
    {
        $extensionUuid = Auth::user()?->extension_uuid;

        if (!$extensionUuid) {
            return null;
        }

        $extension = Extensions::whereKey($extensionUuid)
            ->where('domain_uuid', session('domain_uuid'))
            ->select([
                'extension_uuid',
                'domain_uuid',
                'extension',
                'effective_caller_id_name',
                'do_not_disturb',
                'forward_all_destination',
                'forward_all_enabled',
                'forward_busy_destination',
                'forward_busy_enabled',
                'forward_no_answer_destination',
                'forward_no_answer_enabled',
                'forward_user_not_registered_destination',
                'forward_user_not_registered_enabled',
                'follow_me_enabled',
            ])
            ->first();

        if (!$extension) {
            return null;
        }

        return [
            'extension_uuid' => $extension->extension_uuid,
            'extension' => $extension->extension,
            'name' => $extension->name_formatted,
            'do_not_disturb' => $extension->do_not_disturb === 'true',
            'call_sequence_enabled' => $extension->follow_me_enabled === 'true',
            'forwarding' => [
                $this->formatForwardingStatus($extension, 'forward_all', 'All Calls'),
                $this->formatForwardingStatus($extension, 'forward_busy', 'Busy'),
                $this->formatForwardingStatus($extension, 'forward_no_answer', 'No Answer'),
                $this->formatForwardingStatus($extension, 'forward_user_not_registered', 'Offline'),
            ],
        ];
    }

    private function formatForwardingStatus(Extensions $extension, string $prefix, string $label): array
    {
        $enabledField = "{$prefix}_enabled";
        $actionDisplay = "{$prefix}_action_display";
        $targetName = "{$prefix}_target_name";
        $targetExtension = "{$prefix}_target_extension";

        $target = $extension->{$targetName} ?: $extension->{$targetExtension};
        $targetLabel = $target && $extension->{$actionDisplay}
            ? $extension->{$actionDisplay} . ': ' . $target
            : $target;

        return [
            'key' => $prefix,
            'label' => $label,
            'enabled' => $extension->{$enabledField} === 'true',
            'target' => $targetLabel,
        ];
    }

    public function getApps()
    {
        $apps = [];
        $user = Auth::user();
        $extension = $user->extension ?? null;

        $voicemail = null;
        
        if ($extension) {
            $voicemail = Voicemails::where('domain_uuid',$extension->domain_uuid)->where('voicemail_id',$extension->extension)->first();
        }

        if (userCheckPermission("extension_view")) {
            $apps[] = ['name' => 'Extensions', 'href' => route('extensions.index'), 'icon' => 'ContactPhoneIcon', 'slug' => 'extensions'];
        }
        if (!userCheckPermission("extension_view") && $extension) {
            $apps[] = [
                'name' => 'My Extension',
                'href' => '#',
                'icon' => 'ContactPhoneIcon',
                'slug' => 'my_extension',
                'action' => 'open_extension_modal',
                'extension_uuid' => $extension->extension_uuid,
                'count_label' => 'Ext ' . $extension->extension,
            ];
        }
        if (userCheckPermission("voicemail_view")) {
            $apps[] = ['name' => 'Voicemails', 'href' => route('voicemails.index'), 'icon' => 'VoicemailIcon', 'slug' => 'voicemails'];
        }

        if (!userCheckPermission("voicemail_view") && $voicemail) {
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
            $apps[] = ['name' => 'Call Flows', 'href' => route('call-flows.index'), 'icon' => 'AlternativeRouteIcon', 'slug' => 'call_flows'];
        }
        if (userCheckPermission("fax_view")) {
            $apps[] = ['name' => 'Faxes', 'href' => '/faxes', 'icon' => 'FaxIcon', 'slug' => 'faxes'];
        }
        if (userCheckPermission("messages_view")) {
            $apps[] = ['name' => 'Messages', 'href' => '/messages', 'icon' => 'UsersIcon', 'slug' => 'messages'];
        }
        if (userCheckPermission("whitelisted_numbers_list_view")) {
            $apps[] = ['name' => 'Whitelisted Numbers', 'href' => route('whitelisted-numbers.index'), 'icon' => 'HeartIcon', 'slug' => 'whitelisted_numbers'];
        }
        if (userCheckPermission("wakeup_calls_list_view")) {
            $apps[] = ['name' => 'Wakeup Calls', 'href' => route('wakeup-calls.index'), 'icon' => 'ClockIcon', 'slug' => 'wakeup_calls'];
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
