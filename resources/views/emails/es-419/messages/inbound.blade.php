{{-- email-template
version: 1.0.0
language: es-419
category: messages
subcategory: inbound
format: html
layout: standard
subject: Nuevo mensaje de {{ $attributes['source'] ?? '—' }}
description: Mensaje entrante reenviado por correo electrónico
--}}
@extends('emails.es-419.email_layout')

@section('content')

<p><strong>De:</strong> {{ $attributes['source'] ?? '—' }}</p>
<p><strong>Para:</strong> {{ $attributes['destination'] ?? '—' }}</p>

@if(!empty($attributes['message']))
    <p>{{ $attributes['message'] }}</p>
@else
    <p><em>Sin contenido de texto.</em></p>
@endif

@if(!empty($attributes['inline_images']) && is_array($attributes['inline_images']))
    <p><strong>Imágenes:</strong></p>

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
    <p><strong>Adjuntos:</strong> {{ count($attributes['media']) }}</p>

    <ul>
        @foreach($attributes['media'] as $index => $item)
            <li>
                {{ $item['original_name'] ?? $item['stored_name'] ?? ('Archivo adjunto ' . ($index + 1)) }}
                @if(!empty($item['mime_type']))
                    ({{ $item['mime_type'] }})
                @endif
            </li>
        @endforeach
    </ul>
@endif

@endsection
