<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Messages;
use App\Models\Extensions;
use Illuminate\Http\Request;
use App\Models\DomainSettings;
use App\Models\SmsDestinations;
use App\Notifications\StatusUpdate;
use Illuminate\Support\Facades\Http;
use Propaganistas\LaravelPhone\PhoneNumber;
use Illuminate\Support\Facades\Notification;


class SmsWebhookController extends Controller
{
    // Recieve SMS from the provider and send through Ringotel API
    public function handle(Request $request)
    {
        // $payload = $request->all();
        // Notification::route('mail', 'dexter@stellarvoip.com')
        //      ->notify(new StatusUpdate($request));

        //Check if the request has Unique ID. This will confirm that it 
        //came from the correct source
        // Example of the request that came from TELI:
        //POST /api/sms/webhook HTTP/1.1 Accept: / Content-Length: 120 Content-Type: 
        //application/x-www-form-urlencoded Host: freeswitchpbx.us.nemerald.net 
        //source=6467052267&destination=4243591155&message=2&type=sms
        //&cost=0.000000&unique_id=1c6327d2-a653-4a81-a0b6-8d2e313876d9
        if (!$request->unique_id) {
            return response()->json([
                'error' => 401,
                'message' => 'Unauthorized']);
        }

        // Get domain UUID using destination number from the request
        $smsDestinationModel = SmsDestinations::where('destination', $request->destination)
            ->where('enabled','true')
            ->first();

        // If destination not found send a failed response
        if (is_null($smsDestinationModel)){
            return response('Destination not found');
            Notification::route('mail', 'dexter@stellarvoip.com')
                ->notify(new StatusUpdate('destination not found'));
        }

        //Find Domain to which destination number belongs
        $domainModel = Domain::find($smsDestinationModel->domain_uuid);

        // Get domain App Org ID setting
        $setting = $domainModel->settings()
            ->where('domain_setting_category', 'app shell')
            ->where('domain_setting_subcategory', 'org_id')
            ->get('domain_setting_value')
            ->first();

        $data = array(
            'method' => 'message',
            'params' => array(
                'orgid' => $setting->domain_setting_value,
                'from' => $request->source,
                'to' => $smsDestinationModel->chatplan_detail_data,
                // 'content' => $domainModel->domain_uuid,
                'content' => $request->message,
            )
         );

        $response = Http::ringotel()
            //->dd()
            ->timeout(5)
            ->withBody(json_encode($data),'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                Notification::route('mail', 'dexter@stellarvoip.com')
                ->notify(new StatusUpdate("error"));
                return response()->json([
                    'error' => 401,
                    'message' => 'Unable to send message']);
             })
            ->json();
        
        //Example of succesfull message
        //{"result":{"sessionid":"1649368248560-f92a642d026618b5fe"}}

        //If message sucesfully assign success status
        if (isset($response['result'])){
            $status = "success";
        } else{
            $status = "failed";
        }

        //Get Extension model
        $ext_model = Extensions::where('domain_uuid', $smsDestinationModel->domain_uuid)
            ->where('extension', $smsDestinationModel->chatplan_detail_data)
            ->first();

        // Store message in database
        $message = new Messages;
        $message->extension_uuid = $ext_model->extension_uuid;
        $message->domain_uuid = $smsDestinationModel->domain_uuid;
        $message->source = $request->source;
        $message->destination = $request->destination;
        $message->message = $request->message;
        $message->direction = 'in';
        $message->type = 'sms';
        $message->status = $status;
        $message->save();

        
        // Notification::route('mail', 'dexter@stellarvoip.com')
        //      ->notify(new StatusUpdate($message));

        // if($payload['type'] == 'charge.succeeded'){
        //    Notification::route('nexmo', config('services.nexmo.sms_to'))
        //                 ->notify(new NewSaleOccurred($payload));
        // }

        return response('Webhook received');
    }

    // Receive SMS from Ringotel and send to the provider
    public function messageFromRingotel(Request $request){
        //$payload = json_decode(file_get_contents('php://input'));
        $rawdata = file_get_contents("php://input");
        // $rawdata = '{"method":"message","api_key":"6PLZo32GaKExdcWKC06m4gAccAahYdG7okNEG3EeDAeYSCp030rNDdJ5QXqcHzfj",
        //     "params":{"from":"140","to":"6467052267","type":1,"ownerid":"16276636335171355647",
        //         "userid":"16493663769626583076","content":"Dhjddh","orgid":"16493662427216232141"}}';
        $message = json_decode($rawdata,true);

        //Check message API key to authorize this method
        if (!isset($message['api_key'])){
            return response('No API Key Provided');
        } elseif ($message['api_key'] != env("RINGOTEL_TOKEN")){
            Notification::route('mail', 'dexter@stellarvoip.com')
                ->notify(new StatusUpdate("Outbound SMS: Wrong API Key"));
            return response('Outbound SMS: Wrong API Key');
        }

        //Create libphonenumber object for destination number
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        $phoneNumberObject = $phoneNumberUtil->parse($message['params']['to'], 'US');

        //Validate the destination number
        if (!$phoneNumberUtil->isValidNumber($phoneNumberObject)){
            return response('Number is not valid');
        }

        //Get user's domain settings
        $domainSetting = DomainSettings::where('domain_setting_subcategory', 'org_id')
            ->where('domain_setting_value',$message['params']['orgid'])
            ->first();

        // Get SMS Destinations model that belongs to the user
        $smsDestinationModel = SmsDestinations::where('domain_uuid', $domainSetting->domain_uuid)
            ->where('chatplan_detail_data',$message['params']['from'])
            ->first();

        //Create libphonenumber object for Caller ID number
        $sourcePhoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        $sourcePhoneNumberObject = $sourcePhoneNumberUtil->parse($smsDestinationModel->destination, 'US');

        //Validate the destination number
        if (!$sourcePhoneNumberUtil->isValidNumber($sourcePhoneNumberObject)){
            return response('Caller ID is not valid');
        }
        // dd($sourcePhoneNumberObject);

        // Send text message through Teli API
        if ($message['method'] == "message"){
            $response = Http::asForm()->post('https://api.teleapi.net/sms/send?token='. env('TELI_TOKEN'), [
                "source" => $sourcePhoneNumberObject->getNationalNumber(),
                "destination" => $phoneNumberObject->getNationalNumber(),
                "message" => $message['params']['content']
            ]);
        }

        // if method is "read" send  
        if ($message['method'] == "read"){
            // Process read response
        }

        // if method is "delivered" send  
        if ($message['method'] == "delivered"){
            // Process delivered response
        }

        //dd($response->body());

        //Get Extension model
        $ext_model = Extensions::where('domain_uuid', $smsDestinationModel->domain_uuid)
        ->where('extension', $message['params']['from'])
        ->first();

        //Get result
        if ($response['status'] == 'error'){
            $status = $response['data'];
        } elseif ($response['status'] == 'success') {
            $status = "success";
        }
        
        // Store message in database
        $messageModel = new Messages;
        $messageModel->extension_uuid = $ext_model->extension_uuid;
        $messageModel->domain_uuid = $smsDestinationModel->domain_uuid;
        $messageModel->source = $sourcePhoneNumberObject->getNationalNumber();
        $messageModel->destination = $phoneNumberObject->getNationalNumber();
        $messageModel->message = $message['params']['content'];
        $messageModel->direction = 'out';
        $messageModel->type = 'sms';
        $messageModel->status = $status;
        $messageModel->save();

        // Notification::route('mail', 'dexter@stellarvoip.com')
        //           ->notify(new StatusUpdate($response));
    }


}
