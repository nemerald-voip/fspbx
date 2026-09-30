{{-- email-template
format: text
layout: none
--}}
RAPPORT DE TRANSCRIPTION D’APPEL
=========================
Date :      {{ $data['date'] }}
Durée :  {{ $data['duration'] }}
Sentiment : {{ ['positive' => 'Positif', 'negative' => 'Négatif', 'neutral' => 'Neutre'][strtolower($data['sentiment'])] ?? $data['sentiment'] }}

SYNTHÈSE
-----------------
"{{ $data['summary'] }}"

@if(!empty($data['action_items']))
ACTIONS ET PROCHAINES ÉTAPES
-------------------------
@foreach($data['action_items'] as $item)
[ ] @if($item['owner'])({{ $item['owner'] }}) @endif{{ $item['description'] }}
@endforeach
@endif
