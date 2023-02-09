<?php

namespace App\Http\Controllers;

use App\Models\EmailQueue;

class EmailQueueController extends Controller
{
    public function index()
    {
        // Check permissions
        if (! userCheckPermission("email_queue_view")) {
            return redirect('/');
        }

        $emailQueues = EmailQueue::query()->paginate();

        return view('layouts.emailQueues.list', compact('emailQueues'));
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

    public function updateStatus($id, $status = null)
    {
        EmailQueue::query()
            ->where('email_queue_uuid', $id)
            ->update([
                'email_status' => $status,
            ]);

        return redirect()->back();
    }
}
