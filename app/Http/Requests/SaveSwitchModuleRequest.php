<?php

namespace App\Http\Requests;

use App\Models\SwitchModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSwitchModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission($this->isMethod('PUT') ? 'module_edit' : 'module_add');
    }

    public function rules(): array
    {
        $module = $this->route('module');
        $uniqueName = Rule::unique('v_modules', 'module_name');

        if ($module instanceof SwitchModule) {
            $uniqueName->ignore($module->module_uuid, 'module_uuid');
        }

        return [
            'module_label' => ['required', 'string', 'max:255'],
            'module_name' => ['required', 'string', 'max:255', 'regex:/^mod_[A-Za-z0-9_]+$/D', $uniqueName],
            'module_category' => ['required', 'string', 'max:255'],
            'module_order' => ['nullable', 'numeric'],
            'module_enabled' => ['required', Rule::in(['true', 'false'])],
            'module_default_enabled' => ['required', Rule::in(['true', 'false'])],
            'module_description' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'module_label' => __('Label'),
            'module_name' => __('Module name'),
            'module_category' => __('Category'),
            'module_order' => __('Order'),
            'module_enabled' => __('Autoload enabled'),
            'module_default_enabled' => __('Default autoload enabled'),
            'module_description' => __('Description'),
        ];
    }
}
