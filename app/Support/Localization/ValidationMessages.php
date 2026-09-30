<?php

namespace App\Support\Localization;

class ValidationMessages
{
    public static function common(): array
    {
        return [
            'required' => __('The :attribute field is required.'),
            'present' => __('The :attribute field must be present.'),
            'required_with' => __('The :attribute field is required when :values is present.'),
            'required_without' => __('The :attribute field is required when :values is not present.'),
            'file' => __('The :attribute must be a file.'),
            'mimes' => __('The :attribute must be a file of type: :values.'),
            'extensions' => __('The :attribute must have one of the following extensions: :values.'),
            'required_if' => __('The :attribute field is required when :other is :value.'),
            'numeric' => __('The :attribute must be a number.'),
            'email' => __('The :attribute must be a valid email address.'),
            'url' => __('The :attribute must be a valid URL.'),
            'date' => __('The :attribute is not a valid date.'),
            'after_or_equal' => __('The :attribute must be a date after or equal to :date.'),
            'regex' => __('The :attribute format is invalid.'),
            'unique' => __('The :attribute has already been taken.'),
            'between' => [
                'numeric' => __('The :attribute must be between :min and :max.'),
            ],
            'string' => __('The :attribute must be a string.'),
            'array' => __('The :attribute must be an array.'),
            'boolean' => __('The :attribute must be true or false.'),
            'integer' => __('The :attribute must be an integer.'),
            'uuid' => __('The :attribute must be a valid UUID.'),
            'exists' => __('The selected :attribute is invalid.'),
            'distinct' => __('The :attribute field has a duplicate value.'),
            'in' => __('The selected :attribute is invalid.'),
            'min' => [
                'string' => __('The :attribute must be at least :min characters.'),
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
