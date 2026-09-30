{{-- email-template
version: 1.0.0
language: ru
category: messages
subcategory: inbound
format: html
layout: standard
subject: Новое сообщение от {{ $attributes['source'] ?? '—' }}
description: Входящее сообщение, пересланное на электронную почту
--}}
@extends('emails.ru.email_layout')

@section('content')

<p><strong>От:</strong> {{ $attributes['source'] ?? '—' }}</p>
<p><strong>Кому:</strong> {{ $attributes['destination'] ?? '—' }}</p>

@if(!empty($attributes['message']))
    <p>{{ $attributes['message'] }}</p>
@else
    <p><em>Текст отсутствует.</em></p>
@endif

@if(!empty($attributes['inline_images']) && is_array($attributes['inline_images']))
    <p><strong>Изображения:</strong></p>

    @foreach($attributes['inline_images'] as $image)
        <div style="margin: 0 0 16px 0;">
            <img
                src="cid:{{ $image['cid'] }}"
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
    <p><strong>Вложения:</strong> {{ count($attributes['media']) }}</p>

    <ul>
        @foreach($attributes['media'] as $index => $item)
            <li>
                {{ $item['original_name'] ?? $item['stored_name'] ?? ('Вложение ' . ($index + 1)) }}
                @if(!empty($item['mime_type']))
                    ({{ $item['mime_type'] }})
                @endif
            </li>
        @endforeach
    </ul>
@endif

@endsection
