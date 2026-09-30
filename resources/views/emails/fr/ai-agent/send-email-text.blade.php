{{-- email-template
format: text
layout: none
--}}
{{ $attributes['email_subject'] }}

Un agent IA a recueilli les informations suivantes pour le suivi :

@foreach ($attributes['fields'] as $field)
{{ $field['label'] }}: {{ $field['value'] }}
@endforeach
@if (!empty($attributes['notes']))

Informations complémentaires :
{{ $attributes['notes'] }}
@endif
