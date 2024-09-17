<?php

namespace App\Http\Controllers;

use App\Models\MobileAppPasswordResetLinks;
use App\Models\MobileAppUsers;
use Illuminate\Http\Request;
use Inertia\Inertia;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AppsCredentialsController extends Controller
{
    /**
     * Show the credentials view.
     *
     * @param  Request  $request
     * @return \Inertia\Response
     */
    public function getPasswordByToken(Request $request): \Inertia\Response
    {
        $appCredentials = MobileAppPasswordResetLinks::where('token', $request->token)->first();

        // If reset password link not found throw an error
        if (!$appCredentials) {
            abort(403, 'The link does not exist or expired. Contact your administrator');
        }

        $extension = $appCredentials->extension()->first();
        $extensionDomain = $extension->domain()->first();

        return Inertia::render('Auth/MobileAppGetPassword',
            [
                'display_name' => $extension->effective_caller_id_name,
                'domain' => $extensionDomain->domain_name,
                'username' => $extension->extension,
                'extension' => $extension->extension,
                'routes' => [
                    'retrieve_password' => route('appsRetrievePasswordByToken', $request->token),
                ]
            ]);
    }

    /**
     * Attempt to retrieve the mobile app password.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function retrievePasswordByToken(Request $request): \Illuminate\Http\JsonResponse
    {
        $appCredentials = MobileAppPasswordResetLinks::where('token', $request->token)->first();

        // If reset password link not found throw an error
        if (!$appCredentials) {
            abort(403, 'The link does not exist or expired. Contact your administrator');
        }

        try {
            $appUser = MobileAppUsers::where('extension_uuid', $appCredentials->extension_uuid)->first();
            $response = appsResetPassword($appUser->org_id, $appUser->user_id, true);
            $qrcode = QrCode::format('png')->generate('{"domain":"' . $response['result']['domain'] .
                    '","username":"' .$response['result']['username'] . '","password":"'.  $response['result']['password'] . '"}');

            MobileAppPasswordResetLinks::where('token', $request->token)->delete();

            return response()->json([
                'qrcode' => ($qrcode!= "") ? base64_encode($qrcode) : null,
                'password' => $response['result']['password']
            ]);
        } catch (\Exception $e) {
            logger($e);
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to retrieve credentials']]
            ], 500);
        }
    }
}
