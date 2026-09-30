{{-- email-template
format: text
layout: none
--}}
Seu fax para {{ $attributes['fax_destination'] }} foi entregue.

O fax foi transmitido com sucesso em {{ $attributes['fax_date'] ?? now()->format('Y-m-d H:i') }}.

@if (!empty($attributes['fax_pages']))
Páginas enviadas: {{ $attributes['fax_pages'] }}@if (isset($attributes['fax_total_pages']) && $attributes['fax_total_pages'] !== $attributes['fax_pages']) de {{ $attributes['fax_total_pages'] }}@endif.
@endif
@if (!empty($attributes['fax_duration_formatted']))
Duração: {{ $attributes['fax_duration_formatted'] }}.
@endif

O fax transmitido está anexado a este e-mail para seus registros.

Obrigado,
A equipe de {{ config('app.name', 'Laravel') }}
