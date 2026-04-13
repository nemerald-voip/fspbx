@extends('emails.email_layout')

@section('content')

<p><strong>From:</strong> {{ $attributes['source'] ?? '—' }}</p>
<p><strong>To:</strong> {{ $attributes['destination'] ?? '—' }}</p>

@if(!empty($attributes['message']))
    <p>{{ $attributes['message'] }}</p>
@else
    <p><em>No text content.</em></p>
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