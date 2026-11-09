<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SendMessageRequest;
use App\Services\Messaging\Outbound\ApiMessageService;

/**
 * @group Messages
 * @authenticated
 */
class MessageController extends Controller
{
    /**
     * Send an SMS or MMS
     *
     * Send a text message or attachments from an enabled number in Message Settings.
     * Requires access to the account and the messages_send permission.
     * A 200 response means FS PBX accepted your request; it does not confirm delivery.
     * Save data.id to match the message with delivery callbacks and message logs.
     * API messages appear in Status > Logs > Messages, but not in member conversations.
     *
     * Both from and to must use E.164 format: + followed by the country code and
     * phone number, such as +12025550100, without spaces or punctuation. National
     * numbers are not accepted. The account's country setting is not used.
     * Phone numbers in responses and delivery callbacks use E.164.
     *
     * To receive delivery updates, select an account or custom webhook for the
     * sending number in Message Settings. Verify callbacks using the Signing secret
     * in Account Settings > Messaging. Without a webhook, check results in message logs.
     *
     * Idempotency-Key is optional. Reuse it with the same request, account, API user,
     * and endpoint for up to 24 hours to retry without creating another message.
     * Different message details with the same key return 409. Use a new key for each
     * new message. A repeated response shows the original acceptance, not current
     * delivery status. On a 503 response, retry with the same key and body.
     * Validation errors return 400 with an error object identifying the invalid field.
     *
     * @urlParam domain_uuid string required Account UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @header Idempotency-Key 32dcb5f0-70e1-44ed-a034-493984af48d1
     * @response 200 {"data":{"id":"751ec0cb-4a4e-4462-85ac-f333cbaf77ee","record_type":"message","direction":"outbound","type":"SMS","from":{"phone_number":"+12125550123"},"to":[{"phone_number":"+12125550124","status":"queued"}],"text":"Your appointment is confirmed.","media":[],"received_at":"2026-11-05T00:00:00+00:00","sent_at":null,"completed_at":null,"errors":[]}}
     * @response 400 {"error":{"type":"invalid_request_error","message":"Provide text or at least one media URL.","code":"invalid_parameter","param":"text"}}
     * @response 401 {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 {"error":{"code":"forbidden_permission","permission":"messages_send"}}
     * @response 409 {"error":{"type":"invalid_request_error","message":"Idempotency-Key was already used with a different request.","code":"idempotency_conflict","param":"Idempotency-Key"}}
     * @response 503 {"error":{"type":"api_error","message":"Unable to queue the message. Retry with the same Idempotency-Key.","code":"queue_unavailable"}}
     */
    public function store(SendMessageRequest $request, ApiMessageService $messages)
    {
        return response()->json($messages->submit($request));
    }

    /**
     * Send a group MMS
     *
     * Send a message to a shared group conversation. Use an array for to, with at
     * least two distinct recipient numbers excluding the sender. Repeated numbers
     * count once. Sinch supports up to 20 recipients; supported Bandwidth local
     * numbers support up to 10. Other providers and Bandwidth toll-free numbers do
     * not support group MMS. Text-only groups are also MMS.
     *
     * The from number and every number in to must use E.164 format: + followed by
     * the country code and phone number, without spaces or punctuation. National
     * numbers are not accepted. Responses and delivery callbacks use E.164 phone numbers.
     *
     * Requires account access and messages_send. Authentication, optional
     * Idempotency-Key, and delivery callbacks work as described for Send an SMS or
     * MMS. The response also includes group_id and participants. Check each recipient's
     * status in delivery callbacks, as group members may have different results.
     *
     * @urlParam domain_uuid string required Account UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @bodyParam to string[] required External recipient numbers. Every number must use E.164 format: + followed by the country code and phone number, without spaces or punctuation. Example: ["+12125550124","+12125550125"]
     * @bodyParam type string Optional; must be MMS when supplied. Example: MMS
     * @header Idempotency-Key 32dcb5f0-70e1-44ed-a034-493984af48d1
     * @response 200 {"data":{"id":"751ec0cb-4a4e-4462-85ac-f333cbaf77ee","record_type":"message","direction":"outbound","type":"MMS","from":{"phone_number":"+12125550123"},"to":[{"phone_number":"+12125550124","status":"queued"},{"phone_number":"+12125550125","status":"queued"}],"text":"Hello everyone","media":[],"received_at":"2026-11-05T00:00:00+00:00","sent_at":null,"completed_at":null,"errors":[],"group_id":"3241c8f9-988c-588c-a039-1132d1969876","participants":["+12125550123","+12125550124","+12125550125"]}}
     * @response 400 {"error":{"type":"invalid_request_error","message":"Group messaging is not available for this sending number.","code":"invalid_parameter","param":"to"}}
     */
    public function group(SendMessageRequest $request, ApiMessageService $messages)
    {
        return response()->json($messages->submit($request));
    }
}
