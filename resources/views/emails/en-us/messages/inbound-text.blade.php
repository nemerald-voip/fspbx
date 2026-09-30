{{-- email-template
format: text
layout: none
--}}
From: {{ $attributes['source'] ?? '—' }}
To: {{ $attributes['destination'] ?? '—' }}

@if(!empty($attributes['message']))
{{ $attributes['message'] }}
@else
No text content.
@endif

@if(!empty($attributes['media']) && is_array($attributes['media']))
Attachments: {{ count($attributes['media']) }}
@foreach($attributes['media'] as $index => $item)
- {{ $item['original_name'] ?? $item['stored_name'] ?? ('Attachment ' . ($index + 1)) }}@if(!empty($item['mime_type'])) ({{ $item['mime_type'] }})@endif
@endforeach
@endif
