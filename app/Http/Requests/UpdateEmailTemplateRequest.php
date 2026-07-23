<?php

namespace App\Http\Requests;

class UpdateEmailTemplateRequest extends StoreEmailTemplateRequest
{
    public function authorize(): bool
    {
        return auth()->check() && userCheckPermission('email_templates_update');
    }
}
