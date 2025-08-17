<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use App\Models\GatewaySetting;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\UpdatePaymentGatewayRequest;

class PaymentGatewayController extends Controller
{
    public function update(UpdatePaymentGatewayRequest $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();

            // 1) toggle the gateway
            $gateway = PaymentGateway::findOrFail($data['uuid']);
            $gateway->is_enabled = filter_var($data['status'], FILTER_VALIDATE_BOOLEAN);
            $gateway->save();

            // 2) upsert its four settings
            $settings = [
                'sandbox_secret_key'        => $data['sandbox_secret_key'],
                'sandbox_publishable_key'   => $data['sandbox_publishable_key'],
                'live_mode_secret_key'      => $data['live_mode_secret_key'],
                'live_mode_publishable_key' => $data['live_mode_publishable_key'],
            ];

            foreach ($settings as $key => $value) {
                $setting = GatewaySetting::firstOrNew([
                    'gateway_uuid' => $gateway->uuid,
                    'setting_key'  => $key,
                ]);

                $setting->setting_value = $value;

                if (! $setting->exists) {
                    $setting->uuid = (string) Str::uuid();
                }

                $setting->save();
            }

            DB::commit();

            return response()->json([
                'messages' => [
                    'server' => [
                        'Settings updated successfully.'
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            // log exactly where it failed
            logger($e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'success' => false,
                'errors'  => [
                    'server' => [
                        'Server returned an error while processing your request.'
                    ],
                ],
            ], 500);
        }
    }
}
