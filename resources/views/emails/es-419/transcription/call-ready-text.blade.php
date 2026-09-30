{{-- email-template
format: text
layout: none
--}}
INFORME DE TRANSCRIPCIÓN DE LLAMADA
=========================
Fecha:      {{ $data['date'] }}
Duración:  {{ $data['duration'] }}
Sentimiento: {{ ['positive' => 'Positivo', 'negative' => 'Negativo', 'neutral' => 'Neutral'][strtolower($data['sentiment'])] ?? $data['sentiment'] }}

RESUMEN EJECUTIVO
-----------------
"{{ $data['summary'] }}"

@if(!empty($data['action_items']))
ACCIONES Y PRÓXIMOS PASOS
-------------------------
@foreach($data['action_items'] as $item)
[ ] @if($item['owner'])({{ $item['owner'] }}) @endif{{ $item['description'] }}
@endforeach
@endif
