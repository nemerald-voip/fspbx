<?php

namespace App\Http\Controllers;

use App\Models\EmailQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;

class EmailQueueController extends Controller
{
    public function index(Request $request)
    {
        // Check permissions
        if (! userCheckPermission("email_queue_view")) {
            return redirect('/');
        }

        $statuses = ['all' => 'Show All', 'sent' => 'Sent', 'waiting' => 'Waiting', 'null' => 'Blank'];
        $scopes = ['global', 'local'];
        $selectedStatus = $request->get('status') ?: 'all';
        $searchString = $request->get('search');
        $selectedScope = $request->get('scope', 'local');

        $emailQueuesQuery = EmailQueue::query();
        $domainUuid = Session::get('domain_uuid');
        if (in_array($selectedScope, $scopes) && $selectedScope == 'local') {
            $emailQueuesQuery
                ->where('domain_uuid', $domainUuid);
        } else {
            $emailQueuesQuery
                ->join('v_domains','v_domains.domain_uuid','=','v_email_queue.domain_uuid');
        }
        if (array_key_exists($selectedStatus, $statuses) && $selectedStatus != 'all') {
            if ($selectedStatus === 'null') {
                $emailQueuesQuery
                    ->where(function ($q) {
                        $q->where('email_status', '')
                            ->orWhereNull('email_status');
                    });
            } else {
                $emailQueuesQuery
                    ->where('email_status', $selectedStatus);
            }
        }
        if ($searchString) {
            $emailQueuesQuery->where(function ($query) use ($searchString) {
                return $query
                    ->where('hostname', 'like', '%'.strtolower($searchString).'%')
                    ->orWhere('email_from', 'like', '%'.strtolower($searchString).'%')
                    ->orWhere('email_to', 'like', '%'.strtolower($searchString).'%')
                    ->orWhere('email_subject', 'like', '%'.strtolower($searchString).'%');
            });
        }

        $emailQueues = $emailQueuesQuery->orderBy('email_date', 'desc')->paginate()->onEachSide(1);

        $domain_uuid = Session::get('domain_uuid');
        $time_zone = get_local_time_zone($domain_uuid);
        foreach ($emailQueues as $emailQueue) {
            // Try to convert the date to human redable format
            $emailQueue->email_date = Carbon::parse($emailQueue->email_date)->setTimezone($time_zone);
            // decode a MIME header field to its original character set and content. 
            $emailQueue->email_subject = iconv_mime_decode($emailQueue->email_subject);
        }


        return view('layouts.emailqueue.list', compact('emailQueues', 'searchString', 'statuses', 'selectedStatus', 'selectedScope'));
    }

    public function delete($id)
    {
        EmailQueue::query()->where('email_queue_uuid', $id)->delete();

        return response()->json([
            'status' => 200,
            'success' => [
                'message' => 'Selected email queue has been deleted'
            ]
        ]);
    }

    public function updateStatus(EmailQueue $emailQueue, $status = null)
    {
        $emailQueue->update([
            'email_status' => $status,
        ]);

        return redirect()->back();
    }
}
