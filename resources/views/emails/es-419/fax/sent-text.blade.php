{{-- email-template
format: text
layout: none
--}}
Tu fax a {{ $attributes['fax_destination'] }} fue entregado.

El fax se transmitió correctamente el {{ $attributes['fax_date'] ?? now()->format('Y-m-d H:i') }}.

@if (!empty($attributes['fax_pages']))
Páginas enviadas: {{ $attributes['fax_pages'] }}@if (isset($attributes['fax_total_pages']) && $attributes['fax_total_pages'] !== $attributes['fax_pages']) de {{ $attributes['fax_total_pages'] }}@endif.
@endif
@if (!empty($attributes['fax_duration_formatted']))
Duración: {{ $attributes['fax_duration_formatted'] }}.
@endif

El fax transmitido está adjunto a este correo para tus registros.

Gracias,
El equipo de {{ config('app.name', 'Laravel') }}
