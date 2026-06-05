<?php

namespace App\Http\Controllers;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\GatewaySetting;
use App\Models\PaymentGateway;
use Illuminate\Http\Request;
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

            // 2) Build the new settings, preserving saved secrets when a field
            //    was left blank or excluded by the active mode (see modal UX:
            //    secrets are never pre-filled, blank means "keep current").
            $existing = GatewaySetting::where('gateway_uuid', $gateway->uuid)
                ->pluck('setting_value', 'setting_key')->all();

            $sensitive = [
                'sandbox_secret_key', 'live_mode_secret_key', 'webhook_secret',
                'sandbox_publishable_key', 'live_mode_publishable_key',
            ];

            $settingsPayload = $existing; // start from what's saved
            foreach (Arr::except($data, ['uuid', 'status']) as $key => $value) {
                if ($value !== null && $value !== '') {
                    $settingsPayload[$key] = $value;            // explicit new value wins
                } elseif (! in_array($key, $sensitive, true)) {
                    $settingsPayload[$key] = $value;            // non-secret fields may be cleared
                }
                // blank sensitive field => keep the existing saved value
            }

            // 3) Erase + reinsert the merged settings
            GatewaySetting::where('gateway_uuid', $gateway->uuid)->delete();

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


    /**
     * Test a Stripe secret key by pinging the Balance endpoint. Uses the key
     * supplied in the request, or falls back to the saved key for that mode
     * when the field was left blank. Returns 200 with an `ok` flag either way
     * so the UI can render the result inline.
     */
    public function test(Request $request)
    {
        $data = $request->validate([
            'uuid'       => ['nullable', 'uuid', 'exists:payment_gateways,uuid'],
            'mode'       => ['required', 'in:test,live'],
            'secret_key' => ['nullable', 'string'],
        ]);

        $key = $data['secret_key'] ?? null;

        if (empty($key) && ! empty($data['uuid'])) {
            $settingKey = $data['mode'] === 'test' ? 'sandbox_secret_key' : 'live_mode_secret_key';
            $key = GatewaySetting::where('gateway_uuid', $data['uuid'])
                ->where('setting_key', $settingKey)
                ->value('setting_value');
        }

        if (empty($key)) {
            return response()->json(['ok' => false, 'message' => 'Enter a secret key to test.']);
        }

        try {
            $stripe  = new \Stripe\StripeClient(['api_key' => $key]);
            $balance = $stripe->balance->retrieve();
            $live    = (bool) ($balance->livemode ?? false);

            if ($live !== ($data['mode'] === 'live')) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Key is valid, but it is a ' . ($live ? 'LIVE' : 'TEST')
                        . ' key while ' . strtoupper($data['mode']) . ' mode is selected.',
                ]);
            }

            return response()->json([
                'ok'      => true,
                'message' => 'Connected to Stripe in ' . ($live ? 'live' : 'test') . ' mode.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
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
