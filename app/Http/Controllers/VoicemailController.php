<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Voicemails;
use Illuminate\Http\Request;

class VoicemailController extends Controller
{
    /**
     * Upload a voicemail greeting.
     *
     * @return \Illuminate\Http\Response
     */
    public function uploadVoicemailGreeting(Request $request,Voicemails $voicemail)
    {

        $domain = Domain::where('domain_uuid',$voicemail->domain_uuid)->first();

        $path = $request->voicemail_unavailable_upload_file->storeAs(
            $domain->domain_name .'/' . $voicemail->extension->extension,
            'greeting_1.wav',
            'voicemail'
        );

        return response()->json([
            'request' => $request->voicemail_unavailable_upload_file->getClientOriginalName(),
            'voicemail' => $voicemail->voicemail_id,
            'voicemail_uuid' => $voicemail->voicemail_uuid,
            'domain' => $domain->domain_name,
            'path' => $domain->domain_name .'/' . $voicemail->extension->extension,
            // 'organization_name' => $request->organization_name,
            // 'organization_domain' => $request->organization_domain,
            // 'organization_region' => $request->organization_region,
            'message' => 'Unknown error']);
    }
}
