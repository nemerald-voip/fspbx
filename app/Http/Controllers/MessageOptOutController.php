<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManageMessageOptOutsRequest;
use App\Models\MessageSetting;
use App\Services\Messaging\MessageOptOutService;
use App\Services\PhoneNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MessageOptOutController extends Controller
{
    public function index(ManageMessageOptOutsRequest $request, MessageSetting $setting, MessageOptOutService $service)
    {
        $query = $service->query($setting);
        $search = trim((string) ($request->validated()['search'] ?? ''));
        if ($search !== '') {
            // Normalize complete numbers using the account's country; allow partial-number searches too.
            if (app(PhoneNumberService::class)->isValidForDomain($search, $setting->domain_uuid)) {
                $digits = ltrim($service->normalize($search, $setting->domain_uuid), '+');
            } else {
                $digits = preg_match('/^[+0-9\s().-]+$/D', $search) ? preg_replace('/\D+/', '', $search) : '';
            }
            if ($digits === '') {
                $query->whereIn('message_opt_out_uuid', []);
            } else {
                $query->where(function ($query) use ($digits) {
                    $query->where('phone_number', 'like', '%'.$digits.'%')
                        ->orWhere('local_number', 'like', '%'.$digits.'%');
                });
            }
        }

        return response()->json([
            'scope' => $service->scope($setting),
            'data' => $query->orderBy('phone_number')->orderBy('local_number')->orderBy('message_opt_out_uuid')->paginate(50),
        ]);
    }

    public function destroy(ManageMessageOptOutsRequest $request, MessageSetting $setting, string $optOut, MessageOptOutService $service)
    {
        abort_unless($service->query($setting)->where('message_opt_out_uuid', $optOut)->delete(), 404);

        return response()->json(['messages' => ['success' => [__('Opt-out removed.')]]]);
    }

    public function update(ManageMessageOptOutsRequest $request, MessageSetting $setting, MessageOptOutService $service)
    {
        $data = $request->validated();
        $values = preg_split('/[\r\n,;]+/', $data['numbers'], -1, PREG_SPLIT_NO_EMPTY);
        $values = array_values(array_filter(array_map('trim', $values)));
        if (!$values || count($values) > 1000) throw ValidationException::withMessages(['numbers' => [__('Enter between 1 and 1,000 phone numbers.')]]);
        // Validate the entire batch before changing any records.
        $phones = array_values(array_unique(array_map(fn ($value) => $service->normalize($value, $setting->domain_uuid), $values)));
        $count = DB::transaction(fn () => $data['action'] === 'add'
            ? $service->add($setting, $phones)
            : $service->query($setting)->whereIn('phone_number', $phones)->delete());
        return response()->json(['messages' => ['success' => [__('Opt-out list updated. :count entries changed.', ['count' => $count])]]]);
    }
}
