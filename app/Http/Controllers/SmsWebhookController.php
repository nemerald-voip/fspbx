<?php

namespace App\Http\Controllers;

use App\Notifications\StatusUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class SmsWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();
        Notification::route('mail', 'dexter@stellarvoip.com')
            ->notify(new StatusUpdate($payload));

        // if($payload['type'] == 'charge.succeeded'){
        //    Notification::route('nexmo', config('services.nexmo.sms_to'))
        //                 ->notify(new NewSaleOccurred($payload));
        // }

        return response('Webhook received');
    }
}
