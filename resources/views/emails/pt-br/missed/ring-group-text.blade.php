{{-- email-template
format: text
layout: none
--}}
Chamada perdida{{ $attributes['caller_id_number'] ? ' de ' . $attributes['caller_id_number'] : '' }}.

Uma chamada para {{ $attributes['ring_group_display'] ?: 'seu grupo de chamadas' }} não foi atendida.

De: {{ $attributes['caller_display'] ?: 'Chamador desconhecido' }}
Para: {{ $attributes['ring_group_display'] ?: 'Grupo de chamadas' }}
@if (!empty($attributes['destination_number']))
Número discado: {{ $attributes['destination_number'] }}
@endif

Obrigado,
A equipe de {{ config('app.name', 'FS PBX') }}
