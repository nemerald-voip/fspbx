<?php

namespace App\Http\Controllers;

use App\Models\FaxQueues;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use libphonenumber\PhoneNumberFormat;

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
        $scopes = ['global', 'local'];
        $selectedStatus = $request->get('status');
        $searchString = $request->get('search');
        $selectedScope = $request->get('scope', 'local');

        // Get local Time Zone
        $timeZone = get_local_time_zone(Session::get('domain_uuid'));
        $domainUuid = Session::get('domain_uuid');
        $faxQueues = FaxQueues::query();
        if (in_array($selectedScope, $scopes) && $selectedScope == 'local') {
            $faxQueues
                ->where('domain_uuid', $domainUuid);
        } else {
            $faxQueues
                ->join('v_domains','v_domains.domain_uuid','=','v_fax_queue.domain_uuid');
        }
        if (array_key_exists($selectedStatus, $statuses) && $selectedStatus != 'all') {
            $faxQueues
                ->where('fax_status', $selectedStatus);
        }
        if ($searchString) {
            $faxQueues->where(function ($query) use ($searchString) {
                $query
                    ->orWhereLike('fax_email_address', strtolower($searchString))
                    ->orWhereLike('fax_caller_id_number', strtolower($searchString));
            });
        }
        $faxQueues = $faxQueues->orderBy('fax_date', 'asc')->paginate(10)->onEachSide(1);

        foreach ($faxQueues as $i => $faxQueue) {
            $faxQueues[$i]['fax_date'] = Carbon::parse($faxQueue['fax_date'])->setTimezone($timeZone);
            $faxQueues[$i]['fax_notify_date'] = Carbon::parse($faxQueue['fax_notify_date'])->setTimezone($timeZone);
            $faxQueues[$i]['fax_retry_date'] = Carbon::parse($faxQueue['fax_retry_date'])->setTimezone($timeZone);
        }

        $data = array();
        $data['faxQueues'] = $faxQueues;
        $data['statuses'] = $statuses;
        $data['selectedStatus'] = $selectedStatus;
        $data['selectedScope'] = $selectedScope;
        $data['searchString'] = $searchString;
        $data['national_phone_number_format'] = PhoneNumberFormat::NATIONAL;

        unset($statuses, $faxQueues, $faxQueue, $domainUuid, $timeZone, $selectedStatus, $searchString, $selectedScope);

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

        if (isset($faxQueue)) {
            $deleted = $faxQueue->delete();
            if ($deleted) {
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

    public function updateStatus(FaxQueues $faxQueue, $status = null)
    {
        $faxQueue->update([
            'fax_status' => $status,
        ]);

        return redirect()->back();
    }
}
