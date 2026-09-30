{{-- email-template
format: text
layout: none
--}}
Chamada abandonada{{ $attributes['caller_id_number'] ? ' de '.$attributes['caller_id_number'] : '' }}.

Uma pessoa saiu de {{ $attributes['queue_display'] }} antes que um agente atendesse.

De: {{ $attributes['caller_display'] }}
Central de Atendimento: {{ $attributes['queue_display'] }}
Motivo: {{ $attributes['departure_reason'] }}
@if (!empty($attributes['wait_duration']))
Tempo de espera: {{ $attributes['wait_duration'] }}
@endif
ID da Chamada: {{ $attributes['call_uuid'] }}

Obrigado,
A equipe de {{ config('app.name', 'FS PBX') }}
