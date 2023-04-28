<?php

namespace App\Http\Controllers;

use message;
use domain_settings;
use App\Models\Domain;
use App\Models\Messages;
use App\Models\Extensions;
use App\Jobs\SendCommioSMS;
use Illuminate\Http\Request;
use App\Models\DomainSettings;
use App\Models\SmsDestinations;
use App\Notifications\StatusUpdate;
use Illuminate\Support\Facades\Log;
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

        //Example of the request that came from THINQ

        // POST /api/sms/webhook HTTP/1.1 Accept: / Accept-Encoding: deflate, gzip
        // Content-Length: 59 Content-Type: application/x-www-form-urlencoded
        // Host: tx01.us.nemerald.net Referer: https://tx01.us.nemerald.net/api/sms/webhook
        // User-Agent: thinq-sms X-Sms-Guid: 4ed4cec6-dc4a-11ec-947c-174dda593dc8
        // from=6467052267&to=6578330000&type=sms&message=test+message

        if (!$request->unique_id && $request->header('user-agent') != "thinq-sms") {
            return response()->json([
                'error' => 401,
                'message' => 'Unauthorized'
            ]);
        }

        // Set the carrier variables and initial validation
        if ($request->unique_id) {
            $carrier = "Teli";
            $destination = $request->destination;
            $from = $request->source;
            $message = $request->message;
            $validation = true;
        } elseif ($request->header('user-agent') == "thinq-sms") {
            $carrier = "Thinq";
            $destination = $request->to;
            $from = $request->from;
            $message = $request->message;
            $validation = true;
        } else {
            $validation = false;
        }

        // Get domain UUID using destination number from the request
        $smsDestinationModel = SmsDestinations::where('destination', $destination)
            ->where('enabled', 'true')
            ->first();

        // If destination validation failed update status
        if (is_null($smsDestinationModel)) {
            $validation = false;
            $status = "Destination not found";
            // Notification::route('mail', 'dexter@stellarvoip.com')
            //     ->notify(new StatusUpdate('destination not found'));
        } else {

            //Find Domain to which destination number belongs
            $domainModel = Domain::find($smsDestinationModel->domain_uuid);

            if (is_null($domainModel)) {
                $validation = false;
                $status = "Domain not found";
            }

            if ($validation) {
                // Get domain App Org ID setting
                $setting = $domainModel->settings()
                    ->where('domain_setting_category', 'app shell')
                    ->where('domain_setting_subcategory', 'org_id')
                    ->get('domain_setting_value')
                    ->first();
            }

            if (is_null($setting)) {
                $validation = false;
                $status = "Org ID not found";
            }

            if ($validation) {
                $data = array(
                    'method' => 'message',
                    'params' => array(
                        'orgid' => $setting->domain_setting_value,
                        'from' => $from,
                        'to' => $smsDestinationModel->chatplan_detail_data,
                        // 'content' => $domainModel->domain_uuid,
                        'content' => $message,
                    )
                );

                $response = Http::ringotel_api()
                    //->dd()
                    ->timeout(5)
                    ->withBody(json_encode($data), 'application/json')
                    ->post('/')
                    ->throw(function ($response, $e) {
                        Notification::route('mail', 'dexter@stellarvoip.com')
                            ->notify(new StatusUpdate("error"));
                        return response()->json([
                            'error' => 401,
                            'message' => 'Unable to send message'
                        ]);
                    })
                    ->json();

                //Example of succesfull message
                //{"result":{"sessionid":"1649368248560-f92a642d026618b5fe"}}
                // Log::alert($response);
                //If message sucesfully sent assign success status
                if (isset($response['result'])) {
                    $status = "success";
                } else {
                    $status = "failed";
                }

                //Get Extension model
                $ext_model = Extensions::where('domain_uuid', $smsDestinationModel->domain_uuid)
                    ->where('extension', $smsDestinationModel->chatplan_detail_data)
                    ->first();
            }
        }

        // Store message in database
        $messageModel = new Messages;
        $messageModel->extension_uuid = (isset($ext_model->extension_uuid)) ? $ext_model->extension_uuid : null;
        $messageModel->domain_uuid = (isset($smsDestinationModel->domain_uuid)) ? $smsDestinationModel->domain_uuid : null;
        $messageModel->source = $from;
        $messageModel->destination = $destination;
        $messageModel->message = $message;
        $messageModel->direction = 'in';
        $messageModel->type = 'sms';
        $messageModel->status = $status;
        $messageModel->save();


        // Notification::route('mail', 'dexter@stellarvoip.com')
        //      ->notify(new StatusUpdate($message));

        // if($payload['type'] == 'charge.succeeded'){
        //    Notification::route('nexmo', config('services.nexmo.sms_to'))
        //                 ->notify(new NewSaleOccurred($payload));
        // }

        return response('Webhook received');
    }

    // Receive SMS from Ringotel and send to the provider
    public function messageFromRingotel(Request $request)
    {
        //$payload = json_decode(file_get_contents('php://input'));
        $rawdata = file_get_contents("php://input");
        // $rawdata = '{"method":"message","api_key":"e9bS5f5WNBK4HZRK143Wb3VM1EIk48hDQFvL6MP16UA0VMTUV2QmVdtqFXmf6uKf",
        //     "params":{"from":"300","to":"6467052267","type":1,"ownerid":"16276636335171355647","userid":"16481387548477672383",
        //         "content":"5","orgid":"16467742064165946777"}}';
        $message = json_decode($rawdata, true);

        // Notification::route('mail', 'dexter@stellarvoip.com')
        // ->notify(new StatusUpdate($rawdata));

        // Set initial validation status
        $validation = true;

        //Check message API key to authorize this method
        if (!isset($message['api_key'])) {
            return response('No API Key Provided');
        } elseif ($message['api_key'] != env("RINGOTEL_TOKEN")) {
            $validation = false;
            $status = "Wrong API Key";
        }

        // if method is "typing"
        if ($message['method'] == "typing") {
            return;
        }

        if (isset($message['params']['to'])) {
            //Create libphonenumber object for destination number
            $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();
            $phoneNumberObject = $phoneNumberUtil->parse($message['params']['to'], 'US');

            //Validate the destination number
            if (!$phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                //     Notification::route('mail', 'dexter@stellarvoip.com')
                //   ->notify(new StatusUpdate("number is not valid"));
                $validation = false;
                $status = "Destination number is not a valid US number";
            }
        }

        // if method is "read" send
        if ($message['method'] == "read") {
            // Process read response
            exit();
        }

        // if method is "delivered" send
        if ($message['method'] == "delivered") {
            // Process delivered response
            exit();
        }

        //Get user's domain settings
        $domainSetting = DomainSettings::where('domain_setting_subcategory', 'org_id')
            ->where('domain_setting_value', $message['params']['orgid'])
            ->first();
        if (!$domainSetting) {
            $validation = false;
            $status = "Domain not found";
        }

        if ($domainSetting) {
            // Get SMS Destinations model that belongs to the user
            $smsDestinationModel = SmsDestinations::where('domain_uuid', $domainSetting->domain_uuid)
                ->where('chatplan_detail_data', $message['params']['from'])
                ->first();
        }

        if (!$smsDestinationModel) {
            $validation = false;
            $status = "SMS user not found";
        }

        if ($smsDestinationModel) {
            //Create libphonenumber object for Caller ID number
            $sourcePhoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();
            $sourcePhoneNumberObject = $sourcePhoneNumberUtil->parse($smsDestinationModel->destination, 'US');

            //Validate the source number
            if (!$sourcePhoneNumberUtil->isValidNumber($sourcePhoneNumberObject)) {
                $validation = false;
                $status = "Source number is not a valid US number";
            }

            //Get Extension model
            $ext_model = Extensions::where('domain_uuid', $smsDestinationModel->domain_uuid)
                ->where('extension', $message['params']['from'])
                ->first();

            if (!$ext_model) {
                $validation = false;
                $status = "User extension not found";
            }

            //Assign a provider
            $carrier =  $smsDestinationModel->carrier;
        }

        // Send text message through Teli API
        if ($validation && $message['method'] == "message" && $carrier == "teli") {
            $response = Http::asForm()->post('https://api.teleapi.net/sms/send?token=' . env('TELI_TOKEN'), [
                "source" => $sourcePhoneNumberObject->getNationalNumber(),
                "destination" => $phoneNumberObject->getNationalNumber(),
                "message" => $message['params']['content']
            ]);

            //Get result
            if (isset($response) && $response['status'] == 'error') {
                $status = $response['data'];
            } elseif (isset($response) && $response['status'] == 'success') {
                $status = "success";
            }
        }

        $messageModel = new Messages;
        $messageModel->extension_uuid = (isset($ext_model->extension_uuid)) ? $ext_model->extension_uuid : null;
        $messageModel->domain_uuid = (isset($smsDestinationModel->domain_uuid)) ? $smsDestinationModel->domain_uuid : null;
        $messageModel->source = (isset($sourcePhoneNumberObject)) ? $sourcePhoneNumberObject->getNationalNumber() : "";
        $messageModel->destination = (isset($phoneNumberObject)) ? $phoneNumberObject->getNationalNumber() : "";
        $messageModel->message = $message['params']['content'];
        $messageModel->direction = 'out';
        $messageModel->type = 'sms';
        $messageModel->save();

        // Send text message through Thinq API
        if ($validation && $message['method'] == "message" && $carrier == "thinq") {
            $data = array(
                'from_did' => $sourcePhoneNumberObject->getNationalNumber(),
                'to_did' => $phoneNumberObject->getNationalNumber(),
                "message" => $message['params']['content'],
                "message_uuid" => $messageModel->message_uuid
            );
            SendCommioSMS::dispatch($data)->onQueue('messages');
            $status = "Queued";
        }

        // Updating message status
        $messageModel->status = $status;
        $messageModel->save();

        //dd($response->body());

        // Store message in database


        // Notification::route('mail', 'dexter@stellarvoip.com')
        //           ->notify(new StatusUpdate($response));
    }
}
