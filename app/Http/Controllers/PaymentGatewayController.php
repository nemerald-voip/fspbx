<?php

namespace App\Http\Controllers;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\GatewaySetting;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
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

            // Clear all cached settings for this gateway (requires Redis/Memcached for tags)
            Cache::tags(['gateways', $gateway->slug])->flush();

            // 2) Erase all current settings for this gateway
            GatewaySetting::where('gateway_uuid', $gateway->uuid)->delete();

            // 3) Insert new settings from payload (everything except uuid/status)
            $settingsPayload = Arr::except($data, ['uuid', 'status']);

            if (!empty($settingsPayload)) {
                $now = now();
                $rows = [];
                $ttl = now()->addWeek();
                foreach ($settingsPayload as $key => $value) {
                    $rows[] = [
                        'uuid'          => (string) Str::uuid(),
                        'gateway_uuid'  => $gateway->uuid,
                        'setting_key'   => $key,
                        'setting_value' => $value,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ];

                    // write each setting to cache for one week. format gateways:{slug}:{setting_name}
                    if ($gateway->is_enabled) {
                        Cache::tags(['gateways', $gateway->slug])
                        ->put("gateways:{$gateway->slug}:{$key}", $value, $ttl);
                    }
                    
                }
                GatewaySetting::insert($rows);

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


    public function deactivate()
    {
        $validated = request()->validate([
            'uuid' => ['required', 'uuid', 'exists:payment_gateways,uuid'],
        ]);

        try {
            $gateway = PaymentGateway::findOrFail($validated['uuid']);

            if ($gateway->is_enabled) {
                $gateway->is_enabled = false;
                $gateway->save();
            }

            Cache::tags(['gateways', $gateway->slug])->flush();

            return response()->json([
                'messages' => [
                    'server' => ['Gateway deactivated successfully.'],
                ],
            ], 200);
        } catch (\Throwable $e) {
            logger('PaymentGateway@deactivate error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors'  => [
                    'server' => ['Server returned an error while processing your request.'],
                ],
            ], 500);
        }
    }
}
