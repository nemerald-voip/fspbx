<?php

namespace App\Http\Controllers;

use App\Models\FaxQueues;
use App\Models\Voicemails;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class FaxQueueController extends Controller
{
    /**
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function index(Request $request)
    {
        // Check permissions
        if (!userCheckPermission("fax_queue_all")) {
            return redirect('/');
        }

        $statuses = ['all' => 'Show All', 'sent' => 'Sent', 'waiting' => 'Waiting', 'failed' => 'Failed'];

        // Get local Time Zone
        $timeZone = get_local_time_zone(Session::get('domain_uuid'));

        $domainUuid = Session::get('domain_uuid');
        $faxQueues = FaxQueues::where('domain_uuid', $domainUuid);
        $selectedStatus = $request->get('status');
        if (array_key_exists($selectedStatus, $statuses)) {
            $faxQueues->where('fax_status', $selectedStatus);
        }
        $faxQueues = $faxQueues->orderBy('fax_date', 'asc')->paginate(10)->onEachSide(1);
        foreach ($faxQueues as $i => $faxQueue){
            $faxQueues[$i]['fax_date'] = Carbon::parse($faxQueue['fax_date'])->setTimezone($timeZone);
            $faxQueues[$i]['fax_notify_date'] = Carbon::parse($faxQueue['fax_notify_date'])->setTimezone($timeZone);
            $faxQueues[$i]['fax_retry_date'] = Carbon::parse($faxQueue['fax_retry_date'])->setTimezone($timeZone);
        }

        $data = array();
        $data['faxQueues'] = $faxQueues;
        $data['statuses'] = $statuses;
        $data['selectedStatus'] = $selectedStatus;

        unset($statuses, $faxQueues, $faxQueue, $domainUuid, $timeZone, $selectedStatus);

        $permissions['delete'] = userCheckPermission('fax_queue_delete');
        $permissions['view'] = userCheckPermission('fax_queue_view');

        return view('layouts.faxqueue.list')
            ->with($data)
            ->with('permissions', $permissions);
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function destroy($id)
    {
        $faxQueue = FaxQueues::findOrFail($id);

        if(isset($faxQueue)){
            $deleted = $faxQueue->delete();
            if ($deleted){
                return response()->json([
                    'status' => 200,
                    'success' => [
                        'message' => 'Selected entries have been deleted'
                    ]
                ]);
            } else {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => 'There was an error deleting selected entries extensions'
                    ]
                ]);
            }
        }
    }
}
