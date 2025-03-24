<?php

namespace App\Http\Requests;

use App\Models\Extensions;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class CreateWakeupCallRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'wake_up_time' => [
                'required',
                'date',
                'after:now', // Ensure wake-up time is in the future
            ],
            'extension' => [
                'required',
                'uuid', // Ensure it's a valid UUID
                Rule::exists((new Extensions)->getTable(), 'extension_uuid') // Get correct table name dynamically
            ],
            'status' => [
                'required',
                'string',
                Rule::in(['scheduled', 'in_progress', 'snoozed','completed', 'failed']), // Restrict to valid statuses
            ],
            'recurring' => 'present',
        ];
    }

    public function messages(): array
    {
        return [
            'extension.uuid' => 'The extension field is required',
            'wake_up_time.date' => 'The wake-up time must be a valid date format.',
            'wake_up_time.after' => 'The wake-up time must be in the future.',
            'status.in' => 'The status must be one of: scheduled, in progress, completed, or failed.',
        ];
    }

    public function prepareForValidation(): void
    {
        // Ensure `domain_uuid` is set
        // if (!$this->has('domain_uuid')) {
        //     $this->merge(['domain_uuid' => session('domain_uuid')]);
        // }

        // Convert `recurring` to boolean
        if ($this->has('recurring')) {
            $this->merge(['recurring' => filter_var($this->recurring, FILTER_VALIDATE_BOOLEAN)]);
        }

        // Ensure `status` is lowercase
        // if ($this->has('status')) {
        //     $this->merge(['status' => strtolower($this->status)]);
        // }

        if ($this->has('status')) {
            if ($this->status == 'NULL') {
                $this->merge(['status' => null]);
            }
        }
    }

    /**
     * Sanitize the input field to prevent XSS and remove unwanted characters.
     *
     * @param string $input
     * @return string
     */
    protected function sanitizeInput(string $input): string
    {
        // Trim whitespace
        $input = trim($input);

        // Strip HTML tags
        $input = strip_tags($input);

        // Escape special characters
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        // Remove any non-ASCII characters if necessary (optional)
        $input = preg_replace('/[^\x20-\x7E]/', '', $input);

        return $input;
    }
}
