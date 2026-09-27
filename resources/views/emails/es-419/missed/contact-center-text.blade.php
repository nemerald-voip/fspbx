{{-- email-template
format: text
layout: none
--}}
Llamada abandonada{{ $attributes['caller_id_number'] ? ' de '.$attributes['caller_id_number'] : '' }}.

Una persona abandonó {{ $attributes['queue_display'] }} antes de que respondiera un agente.

De: {{ $attributes['caller_display'] }}
Centro de contacto: {{ $attributes['queue_display'] }}
Motivo: {{ $attributes['departure_reason'] }}
@if (!empty($attributes['wait_duration']))
Tiempo de espera: {{ $attributes['wait_duration'] }}
@endif
ID de llamada: {{ $attributes['call_uuid'] }}

Gracias,
El equipo de {{ config('app.name', 'FS PBX') }}
