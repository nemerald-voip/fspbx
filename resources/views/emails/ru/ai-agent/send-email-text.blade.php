{{-- email-template
format: text
layout: none
--}}
{{ $attributes['email_subject'] }}

ИИ-агент собрал следующие сведения для дальнейшей обработки:

@foreach ($attributes['fields'] as $field)
{{ $field['label'] }}: {{ $field['value'] }}
@endforeach
@if (!empty($attributes['notes']))

Дополнительная информация:
{{ $attributes['notes'] }}
@endif
