@extends('emails.email_layout')

@section('content')

<p><strong>From:</strong> {{ $attributes['source'] ?? '—' }}</p>
<p><strong>To:</strong> {{ $attributes['destination'] ?? '—' }}</p>

@if(!empty($attributes['message']))
    <p>{{ $attributes['message'] }}</p>
@else
    <p><em>No text content.</em></p>
@endif

@if(!empty($attributes['inline_images']) && is_array($attributes['inline_images']))
    <p><strong>Images:</strong></p>

    @foreach($attributes['inline_images'] as $image)
        <div style="margin: 0 0 16px 0;">
            <img
                src="{{ $message->embedData($image['data'], $image['name']) }}"
                alt="{{ $image['name'] }}"
                style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 6px;"
            >
            <div style="font-size: 12px; color: #666; margin-top: 6px;">
                {{ $image['name'] }}
            </div>
        </div>
    @endforeach
@endif

@if(!empty($attributes['media']) && is_array($attributes['media']))
    <p><strong>Attachments:</strong> {{ count($attributes['media']) }}</p>

    <ul>
        @foreach($attributes['media'] as $index => $item)
            <li>
                {{ $item['original_name'] ?? $item['stored_name'] ?? ('Attachment ' . ($index + 1)) }}
                @if(!empty($item['mime_type']))
                    ({{ $item['mime_type'] }})
                @endif
            </li>
        @endforeach
    </ul>
@endif

@endsection