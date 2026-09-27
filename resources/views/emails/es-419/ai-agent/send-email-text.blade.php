{{-- email-template
format: text
layout: none
--}}
{{ $attributes['email_subject'] }}

Un agente de IA recopiló la siguiente información para dar seguimiento:

@foreach ($attributes['fields'] as $field)
{{ $field['label'] }}: {{ $field['value'] }}
@endforeach
@if (!empty($attributes['notes']))

Información adicional:
{{ $attributes['notes'] }}
@endif
