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
}
