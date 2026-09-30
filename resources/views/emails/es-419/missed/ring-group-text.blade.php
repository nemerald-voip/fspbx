{{-- email-template
format: text
layout: none
--}}
Llamada perdida{{ $attributes['caller_id_number'] ? ' de ' . $attributes['caller_id_number'] : '' }}.

Una llamada a {{ $attributes['ring_group_display'] ?: 'tu grupo de timbrado' }} no fue atendida.

De: {{ $attributes['caller_display'] ?: 'Persona que llama desconocida' }}
Para: {{ $attributes['ring_group_display'] ?: 'Grupo de timbrado' }}
@if (!empty($attributes['destination_number']))
Número marcado: {{ $attributes['destination_number'] }}
@endif

Gracias,
El equipo de {{ config('app.name', 'FS PBX') }}
