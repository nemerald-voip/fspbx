{{-- email-template
format: text
layout: none
--}}
{{ $attributes['email_subject'] }}

An AI Agent collected the following information for follow-up:

@foreach ($attributes['fields'] as $field)
{{ $field['label'] }}: {{ $field['value'] }}
@endforeach
@if (!empty($attributes['notes']))

Additional information:
{{ $attributes['notes'] }}
@endif
