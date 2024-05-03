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
use Exception;
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
    protected $currentDestination;


    // Recieve SMS from the provider and send through Ringotel API
    public function handle(Request $request)
    {
        // $payload = $request->all();
        logger($request);

        try {
            // Early exit if unauthorized
            if (!$this->isRequestAuthorized($request)) {
                throw new Exception('Unauthorized request');
            }

            // Determine the carrier and set basic message details
            $this->initializeMessageDetails();

            foreach ($this->destination as $destination) {
                $this->currentDestination = $destination;
                $this->findAndValidateDestination($destination);

                // Send the message through Ringotel API
                if (!$this->sendMessageThroughRingotel($destination)) {
                    throw new Exception("Failed to send message to destination: $destination");
                }
            }





            return response('Webhook received');
        } catch (Exception $e) {
            $error = "*Inbound SMS Failed*: " . $e->getMessage();
            SendSmsNotificationToSlack::dispatch($error)->onQueue('messages');
            logger($error);
            logger($request->all());
            //I need to know the vlaue of current destination

            return $this->unauthorizedResponse();
        }


  

        //     if ($validation) {
        //         $data = array(
        //             'method' => 'message',
        //             'params' => array(
        //                 'orgid' => $setting->domain_setting_value,
        //                 'from' => $from,
        //                 'to' => $smsDestinationModel->chatplan_detail_data,
        //                 // 'content' => $domainModel->domain_uuid,
        //                 'content' => $message,
        //             )
        //         );

        //         $response = Http::ringotel_api()
        //             //->dd()
        //             ->timeout(5)
        //             ->withBody(json_encode($data), 'application/json')
        //             ->post('/')
        //             ->throw(function ($response, $e) {
        //                 Notification::route('mail', 'dexter@stellarvoip.com')
        //                     ->notify(new StatusUpdate("error"));
        //                 return response()->json([
        //                     'error' => 401,
        //                     'message' => 'Unable to send message'
        //                 ]);
        //             })
        //             ->json();

        //         //Example of succesfull message
        //         //{"result":{"sessionid":"1649368248560-f92a642d026618b5fe"}}
        //         // Log::alert($response);
        //         //If message sucesfully sent assign success status
        //         if (isset($response['result'])) {
        //             $status = "success";
        //         } else {
        //             $status = "failed";
        //         }

        //         //Get Extension model
        //         $ext_model = Extensions::where('domain_uuid', $smsDestinationModel->domain_uuid)
        //             ->where('extension', $smsDestinationModel->chatplan_detail_data)
        //             ->first();
        //     }
        // }

        // // Store message in database
        // $messageModel = new Messages;
        // $messageModel->extension_uuid = (isset($ext_model->extension_uuid)) ? $ext_model->extension_uuid : null;
        // $messageModel->domain_uuid = (isset($smsDestinationModel->domain_uuid)) ? $smsDestinationModel->domain_uuid : null;
        // $messageModel->source = $from;
        // $messageModel->destination = $destination;
        // $messageModel->message = $message;
        // $messageModel->direction = 'in';
        // $messageModel->type = 'sms';
        // $messageModel->status = $status;
        // $messageModel->save();


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


    protected function isRequestAuthorized(Request $request)
    {

        $apiKey = config('synch.inbound_api_key');  // Retrieve the API key from the config file
        $messageId = $request->header('messageID');  // Assuming the message ID is in the header
        $verificationToken = $request->header('verificationToken');  // Assuming the token is in the header
        $computedHash = hash('sha256', $apiKey . $messageId);  // Compute the SHA256 hash

        // Try to determine the upstream carrier
        if ($computedHash === $verificationToken) {
            $this->carrier = 'synch';
        }
        if ($request->header('user-agent') == "thinq-sms") {
            $this->carrier = 'thinq';
        }

        return $computedHash === $verificationToken || $request->header('user-agent') == "thinq-sms";
    }

    protected function unauthorizedResponse()
    {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    protected function initializeMessageDetails()
    {
        if ($this->carrier == 'thinq') {
            $this->destination = $this->normalizeDestination(request('to'));
            $this->source = request('from');
            $this->message = request('message');
            return;
        }

        if ($this->carrier == 'synch') {
            $this->destination = $this->normalizeDestination(request('to'));
            $this->source = request('from');
            $this->message = request('text');
            return;
        }

        if (!isset($this->carrier)) {
            throw new Exception("Carrier not supported");
        }
    }

    protected function findAndValidateDestination($destination)
    {
        // Normalize the destination to remove any non-numeric characters and possible prefixes
        $normalizedDestination = preg_replace('/\D/', '', $destination);

        // Assume the number might start with '1' or '+1' which should be considered equivalent
        // If it's exactly 10 digits after stripping, assume it needs the US country code '1'
        if (strlen($normalizedDestination) == 10) {
            $normalizedDestination = '1' . $normalizedDestination; // prepend '1' for US country code
        }

        // If it starts with '1' and is 11 digits, it's already in the full format
        if (strlen($normalizedDestination) == 11 && substr($normalizedDestination, 0, 1) == '1') {
            $possibleFormats = [
                $normalizedDestination,          // as is, e.g., 14235550123
                '+' . $normalizedDestination,    // with +, e.g., +14235550123
                substr($normalizedDestination, 1) // without leading 1, e.g., 4235550123
            ];
        } else {
            $possibleFormats = [$normalizedDestination]; // unlikely but covers non-standard cases
        }

        // Find a match for any of the possible formats
        $smsDestinationModel = SmsDestinations::where(function ($query) use ($possibleFormats) {
            foreach ($possibleFormats as $format) {
                $query->orWhere('destination', $format);
            }
        })
            ->where('enabled', 'true')
            ->first();

        if (!$smsDestinationModel) {
            throw new Exception("From: " . $this->source . " To: " . $destination . " \n No config found for phone number - " . $destination);
        }

        //Find Domain to which destination number belongs
        $this->domain_uuid = $smsDestinationModel->domain_uuid;

        $this->smsDestinationModel = $smsDestinationModel;
    }

    protected function normalizeDestination($destination)
    {
        return is_array($destination) ? $destination : [$destination];
    }

    public function formatPhoneNumberToE164($value)
    {
        //Get libphonenumber object
        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        //try to convert phone number to National format
        try {
            $phoneNumberObject = $phoneNumberUtil->parse($value, 'US');
            if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                $number_formatted = $phoneNumberUtil
                    ->format($phoneNumberObject, PhoneNumberFormat::E164);
            } else {
                $number_formatted = $value;
            }
        } catch (NumberParseException $e) {
            // Do nothing and leave the numbner as is
            $number_formatted = $value;
        }

        return $number_formatted;
    }

    protected function fetchOrgId($destination)
    {
        $setting = DomainSettings::where('domain_uuid', $this->domain_uuid)
            ->where('domain_setting_category', 'app shell')
            ->where('domain_setting_subcategory', 'org_id')
            ->value('domain_setting_value');

        if (is_null($setting)) {
            throw new Exception("From: " . $this->source . " To: " . $destination . " \n Org ID not found");
        }

        return $setting;
    }


    protected function sendMessageThroughRingotel($destination)
    {
        $data = [
            'method' => 'message',
            'params' => [
                'orgid' => $this->fetchOrgId($destination),
                'from' => $this->source,
                'to' => $this->currrentDestination,
                'content' => $this->message,
            ]
        ];

        $response = Http::timeout(5)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->json();

        logger($response);
        return isset($response['result']);
    }
}
