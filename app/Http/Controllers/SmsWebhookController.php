<?php

namespace App\Http\Controllers;


use App\Models\Domain;
use App\Models\Messages;
use App\Models\Extensions;
use Illuminate\Http\Request;
use App\Models\DomainSettings;
use App\Models\SmsDestinations;
use App\Notifications\StatusUpdate;
use libphonenumber\PhoneNumberUtil;
use Illuminate\Support\Facades\Http;
use libphonenumber\PhoneNumberFormat;
use App\Services\SynchMessageProvider;
use App\Services\CommioMessageProvider;
use App\Jobs\SendSmsNotificationToSlack;
use libphonenumber\NumberParseException;
use Illuminate\Support\Facades\Notification;

class SmsWebhookController extends Controller
{
    protected $mobileAppDomainConfig;
    protected $smsDestinationModel;
    protected $domain_uuid;
    protected $message;
    protected $extension_uuid;
    protected $source;
    protected $destination;
    protected $carrier;
    protected $messageProvider;


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
    public function messageFromRingotel()
    {
        $this->message = $this->parseRequest();

        try {
            $this->validateMessage();
            $response = $this->handleMessageType();
            return $response;
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }


    private function parseRequest()
    {
        // Example of the request
        // array (
        //     'method' => 'message',
        //     'api_key' => 'APIKEY',
        //     'params' => 
        //     array (
        //       'domain' => 'apidomain',
        //       'messageid' => '1714513135694-40c3963c2e43ec9a12',
        //       'from' => '202',
        //       'to' => '646705xxxx',
        //       'sessionid' => '1714509056562-7b6f32813bf37ab35e',
        //       'type' => 1,
        //       'userid' => '17145080854xxxxxxxx',
        //       'content' => '1',
        //       'orgid' => '1658381791xxxxxx2',
        //     ),
        //   )  

        $rawdata = file_get_contents("php://input");
        return json_decode($rawdata, true);
    }

    private function validateMessage()
    {
        if (empty($this->message['api_key']) || $this->message['api_key'] != config("ringotel.token")) {
            throw new \Exception("Invalid or missing API Key");
        }

        if (!isset($this->message['params']['to'])) {
            throw new \Exception("Missing destination number");
        }

        $phoneNumberUtil = PhoneNumberUtil::getInstance();
        try {
            $phoneNumberObject = $phoneNumberUtil->parse($this->message['params']['to'], 'US');

            if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                $this->destination = $phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::E164);
            } else {
                $this->destination = $this->message['params']['to'];
                throw new \Exception("Destination phone number (" . $this->message['params']['to'] . ") is not a valid US number");
            }
        } catch (NumberParseException $e) {
            $this->destination = $this->message['params']['to'];
            throw new \Exception("Destination phone number (" . $this->message['params']['to'] . ") is not a valid US number");
        }
    }

    private function handleMessageType()
    {
        switch ($this->message['method']) {
            case 'typing':
            case 'read':
            case 'delivered':
            case 'message':
                return $this->processOutgoingMessage();
            default:
                throw new \Exception("Unsupported method type");
        }
    }

    private function processOutgoingMessage()
    {
        $this->mobileAppDomainConfig = $this->getMobileAppDomainConfig($this->message['params']['orgid']);
        $this->domain_uuid = $this->mobileAppDomainConfig->domain_uuid;
        $this->extension_uuid = $this->getExtensionUuid();

        //Get message config
        $phoneNumberSmsConfig = $this->getPhoneNumberSmsConfig($this->message['params']['from'], $this->domain_uuid);
        $this->carrier =  $phoneNumberSmsConfig->carrier;

        //Determine message provider
        $this->messageProvider = $this->getMessageProvider($this->carrier);

        $phoneNumberUtil = PhoneNumberUtil::getInstance();
        try {
            $phoneNumberObject = $phoneNumberUtil->parse($phoneNumberSmsConfig->destination, 'US');

            if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                $this->source = $phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::E164);
            } else {
                $this->source = $phoneNumberSmsConfig->destination;
                throw new \Exception("Phone number (" . $phoneNumberSmsConfig->destination . ") assigned to extension *" . $this->message['params']['from'] . "* is not a valid US number");
            }
        } catch (NumberParseException $e) {
            $this->source = $phoneNumberSmsConfig->destination;
            throw new \Exception("Phone number (" . $phoneNumberSmsConfig->destination . ") assigned to extension *" . $this->message['params']['from'] . "* is not a valid US number");
        }

        //Store message in the log database
        $message = $this->storeMessage("Queued");

        // Send message
        $this->messageProvider->send($message->message_uuid);

        return response()->json(['status' => 'Message sent']);
    }

    private function getMobileAppDomainConfig($orgId)
    {
        $mobileAppDomainConfig = DomainSettings::where('domain_setting_subcategory', 'org_id')
            ->where('domain_setting_value', $orgId)
            ->with('domain')
            ->first();

        if (!$mobileAppDomainConfig) {
            throw new \Exception("Domain not found");
        }

        return $mobileAppDomainConfig;
    }

    private function getPhoneNumberSmsConfig($from, $domainUuid)
    {
        $phoneNumberSmsConfig = SmsDestinations::where('domain_uuid', $domainUuid)
            ->where('chatplan_detail_data', $from)
            ->first();

        if (!$phoneNumberSmsConfig) {
            throw new \Exception("SMS configuration not found for extension " . $from);
        }

        return $phoneNumberSmsConfig;
    }

    private function getExtensionUuid()
    {
        $extension_uuid = Extensions::where('domain_uuid', $this->domain_uuid)
            ->where('extension', $this->message['params']['from'])
            ->select('extension_uuid')
            ->first()
            ->extension_uuid;
        if (!$extension_uuid) {
            throw new \Exception("Extension " . $this->message['params']['from'] . " not found");
        }

        return $extension_uuid;
    }


    private function storeMessage($status)
    {
        $messageModel = new Messages;
        $messageModel->extension_uuid = (isset($this->extension_uuid)) ? $this->extension_uuid : null;
        $messageModel->domain_uuid = (isset($this->domain_uuid)) ? $this->domain_uuid : null;
        $messageModel->source =  (isset($this->source)) ? $this->source : "";
        $messageModel->destination =  (isset($this->destination)) ? $this->destination : "";
        $messageModel->message = $this->message['params']['content'];
        $messageModel->direction = 'out';
        $messageModel->type = 'sms';
        $messageModel->status = $status;
        $messageModel->save();

        return $messageModel;
    }

    private function handleError(\Exception $e)
    {

        logger($e->getMessage());
        $this->storeMessage($e->getMessage());
        // Log the error or send it to Slack
        $error = isset($this->mobileAppDomainConfig) && isset($this->mobileAppDomainConfig->domain) ?
            "*Outbound SMS Failed*: From: " . $this->message['params']['from'] . " in " . $this->mobileAppDomainConfig->domain->domain_description . " To: " . $this->message['params']['to'] . "\n" . $e->getMessage() :
            "*Outbound SMS Failed*: From: " . $this->message['params']['from'] . " To: " . $this->message['params']['to'] . "\n" . $e->getMessage();

        SendSmsNotificationToSlack::dispatch($error)->onQueue('messages');

        return response()->json(['error' => $e->getMessage()], 400);
    }

    private function getMessageProvider($carrier)
    {
        switch ($carrier) {
            case 'thinq':
                return new CommioMessageProvider();
            case 'synch':
                return new SynchMessageProvider();
                // Add cases for other carriers
            default:
                throw new \Exception("Unsupported carrier");
        }
    }
}
