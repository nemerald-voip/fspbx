<?php

namespace App\Support\Localization;

class ValidationMessages
{
    public static function common(): array
    {
        return [
            'required' => __('The :attribute field is required.'),
            'string' => __('The :attribute must be a string.'),
            'array' => __('The :attribute must be an array.'),
            'boolean' => __('The :attribute must be true or false.'),
            'integer' => __('The :attribute must be an integer.'),
            'uuid' => __('The :attribute must be a valid UUID.'),
            'exists' => __('The selected :attribute is invalid.'),
            'in' => __('The selected :attribute is invalid.'),
            'min' => [
                'numeric' => __('The :attribute must be at least :min.'),
                'array' => __('The :attribute must have at least :min items.'),
            ],
            'max' => [
                'string' => __('The :attribute must not be greater than :max characters.'),
                'numeric' => __('The :attribute must not be greater than :max.'),
                'array' => __('The :attribute must not have more than :max items.'),
                'file' => __('The :attribute must not be greater than :max kilobytes.'),
            ],
        ];
    }
}
