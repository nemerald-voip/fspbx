<?php

namespace App\Http\Requests\Api\V1;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends StoreUserRequest
{
    private ?User $target = null;

    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }
        $uuid = (string) $this->route('user_uuid');
        if (! Str::isUuid($uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid user UUID.', 'invalid_request', 'user_uuid');
        }
        $service = app(UserService::class);
        $this->target = $service->query((string) $this->route('domain_uuid'))->whereKey($uuid)->first();
        if (! $this->target) {
            throw new ApiException(404, 'invalid_request_error', 'User not found.', 'resource_missing', 'user_uuid');
        }
        $service->ensureCanManageTarget($this->user(), $this->target, (string) $this->route('domain_uuid'));

        return true;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['first_name'] = ['sometimes', 'required', 'string', 'max:255'];
        $uniqueEmail = Rule::unique('v_users', 'user_email');
        if ($this->target) {
            $uniqueEmail->ignore($this->target->user_uuid, 'user_uuid');
        }
        $rules['user_email'] = ['sometimes', 'required', 'email', 'max:255', $uniqueEmail];

        return $rules;
    }
}
