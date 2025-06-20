<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\MobileAppUsers;
use App\Services\RingotelApiService;
use App\Models\MobileAppPasswordResetLinks;
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

        return Inertia::render(
            'Auth/MobileAppGetPassword',
            [
                'display_name' => $extension->effective_caller_id_name,
                'domain' => $appCredentials->domain,
                'username' => $extension->extension,
                'extension' => $extension->extension,
                'routes' => [
                    'retrieve_password' => route('appsRetrievePasswordByToken', $request->token),
                ]
            ]
        );
    }

    /**
     * Attempt to retrieve the mobile app password.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function retrievePasswordByToken(Request $request, RingotelApiService $ringotelApiService): \Illuminate\Http\JsonResponse
    {
        try {
            $appCredentials = MobileAppPasswordResetLinks::where('token', $request->token)->first();

            // If reset password link not found throw an error
            if (!$appCredentials) {
                abort(403, 'The link does not exist or expired. Contact your administrator');
            }

            $appUser = MobileAppUsers::where('extension_uuid', $appCredentials->extension_uuid)->first();

            $params = [
                'org_id' => $appUser->org_id,
                'user_id' => $appUser->user_id,
                'noemail' => true,
            ];

            // Send request to reset password
            $user = $ringotelApiService->resetPassword($params);

            $qrcode = QrCode::format('png')->generate('{"domain":"' . $user['domain'] .
                '","username":"' . $user['username'] . '","password":"' .  $user['password'] . '"}');

            MobileAppPasswordResetLinks::where('token', $request->token)->delete();

            return response()->json([
                'qrcode' => ($qrcode != "") ? base64_encode($qrcode) : null,
                'password' => $user['password']
            ]);
        } catch (\Exception $e) {
            logger('AppsCredentialsController@retrievePasswordByToken error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to retrieve credentials']]
            ], 500);
        }
    }
}
