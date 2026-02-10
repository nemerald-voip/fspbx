CALL TRANSCRIPTION REPORT
=========================
Date:      {{ $data['date'] }}
Duration:  {{ $data['duration'] }}
Sentiment: {{ $data['sentiment'] }}

EXECUTIVE SUMMARY
-----------------
"{{ $data['summary'] }}"

@if(!empty($data['action_items']))
ACTION ITEMS & NEXT STEPS
-------------------------
@foreach($data['action_items'] as $item)
[ ] @if($item['owner'])({{ $item['owner'] }}) @endif{{ $item['description'] }}
@endforeach
@endif
