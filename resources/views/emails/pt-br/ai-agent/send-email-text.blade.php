{{-- email-template
format: text
layout: none
--}}
{{ $attributes['email_subject'] }}

Um agente de IA coletou as seguintes informações para acompanhamento:

@foreach ($attributes['fields'] as $field)
{{ $field['label'] }}: {{ $field['value'] }}
@endforeach
@if (!empty($attributes['notes']))

Informações adicionais:
{{ $attributes['notes'] }}
@endif
