{{-- email-template
format: text
layout: none
--}}
De: {{ $attributes['source'] ?? '—' }}
Para: {{ $attributes['destination'] ?? '—' }}

@if(!empty($attributes['message']))
{{ $attributes['message'] }}
@else
Sin contenido de texto.
@endif

@if(!empty($attributes['media']) && is_array($attributes['media']))
Adjuntos: {{ count($attributes['media']) }}
@foreach($attributes['media'] as $index => $item)
- {{ $item['original_name'] ?? $item['stored_name'] ?? ('Archivo adjunto ' . ($index + 1)) }}@if(!empty($item['mime_type'])) ({{ $item['mime_type'] }})@endif
@endforeach
@endif
