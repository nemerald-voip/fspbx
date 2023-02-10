<?php

namespace App\Http\Controllers;

use App\Models\EmailQueue;
use Illuminate\Http\Request;

class EmailQueueController extends Controller
{
    public function index(Request $request)
    {
        // Check permissions
        if (! userCheckPermission("email_queue_view")) {
            return redirect('/');
        }

        $statuses = ['all' => 'Show All', 'sent' => 'Sent', 'waiting' => 'Waiting', '' => 'Blank'];
        $selectedStatus = $request->get('status') ?: 'all';
        $searchString = $request->get('search');

        $emailQueuesQuery = EmailQueue::query();
        if (array_key_exists($selectedStatus, $statuses) && $selectedStatus != 'all') {
            if (! $selectedStatus) {
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
                    ->whereLike('hostname', strtolower($searchString))
                    ->orWhereLike('email_from', strtolower($searchString))
                    ->orWhereLike('email_to', strtolower($searchString))
                    ->orWhereLike('email_subject', strtolower($searchString));
            });
        }

        $emailQueues = $emailQueuesQuery->paginate();

        return view('layouts.emailQueues.list', compact('emailQueues', 'searchString', 'statuses', 'selectedStatus'));
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
