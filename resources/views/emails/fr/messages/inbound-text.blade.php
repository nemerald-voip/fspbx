{{-- email-template
format: text
layout: none
--}}
De : {{ $attributes['source'] ?? '—' }}
À : {{ $attributes['destination'] ?? '—' }}

@if(!empty($attributes['message']))
{{ $attributes['message'] }}
@else
Aucun contenu textuel.
@endif

@if(!empty($attributes['media']) && is_array($attributes['media']))
Pièces jointes : {{ count($attributes['media']) }}
@foreach($attributes['media'] as $index => $item)
- {{ $item['original_name'] ?? $item['stored_name'] ?? ('Pièce jointe ' . ($index + 1)) }}@if(!empty($item['mime_type'])) ({{ $item['mime_type'] }})@endif
@endforeach
@endif
