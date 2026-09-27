{{-- email-template
format: text
layout: none
--}}
RELATÓRIO DE TRANSCRIÇÃO DE CHAMADA
=========================
Data:      {{ $data['date'] }}
Duração:  {{ $data['duration'] }}
Sentimento: {{ ['positive' => 'Positivo', 'negative' => 'Negativo', 'neutral' => 'Neutro'][strtolower($data['sentiment'])] ?? $data['sentiment'] }}

RESUMO EXECUTIVO
-----------------
"{{ $data['summary'] }}"

@if(!empty($data['action_items']))
AÇÕES E PRÓXIMAS ETAPAS
-------------------------
@foreach($data['action_items'] as $item)
[ ] @if($item['owner'])({{ $item['owner'] }}) @endif{{ $item['description'] }}
@endforeach
@endif
