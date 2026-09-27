{{-- email-template
format: text
layout: none
--}}
От: {{ $attributes['source'] ?? '—' }}
Кому: {{ $attributes['destination'] ?? '—' }}

@if(!empty($attributes['message']))
{{ $attributes['message'] }}
@else
Текст отсутствует.
@endif

@if(!empty($attributes['media']) && is_array($attributes['media']))
Вложения: {{ count($attributes['media']) }}
@foreach($attributes['media'] as $index => $item)
- {{ $item['original_name'] ?? $item['stored_name'] ?? ('Вложение ' . ($index + 1)) }}@if(!empty($item['mime_type'])) ({{ $item['mime_type'] }})@endif
@endforeach
@endif
