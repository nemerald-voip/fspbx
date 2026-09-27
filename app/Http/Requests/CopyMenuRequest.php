<?php

namespace App\Http\Requests;

class CopyMenuRequest extends SaveMenuRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['menu_language']);

        return $rules;
    }
}
