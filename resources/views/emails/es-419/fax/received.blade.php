{{-- email-template
version: 1.0.0
language: es-419
category: fax
subcategory: received
format: html
layout: standard
subject: Fax recibido para {{ $attributes['fax_destination'] }}
description: Notificación de fax recibido
--}}
@extends('emails.es-419.email_layout')

@section('content')
<h1>Fax recibido{{ $attributes['caller_id_number'] ? ' de ' . $attributes['caller_id_number'] : '' }}.</h1>

<p>Se recibió un nuevo fax para {{ $attributes['fax_destination'] }} y está adjunto a este correo.</p>

<ul>
    {{-- <li><strong>Domain:</strong> {{ $attributes['domain_name'] ?? '' }}</li> --}}
    <li><strong>De:</strong> {{ $attributes['caller_display'] }}</li>
    <li><strong>Para:</strong> {{ $attributes['fax_destination'] }}</li>
    <li><strong>Páginas:</strong> {{ $attributes['fax_pages'] ?? '' }}</li>
    @if (!empty($attributes['fax_date']))
        <li><strong>Recibido:</strong> {{ $attributes['fax_date'] }}</li>
    @endif
    {{-- <li><strong>Status:</strong> {{ $attributes['fax_result_text'] ?? '' }}</li> --}}
</ul>

@if (!empty($attributes['is_test']))
    <p><strong>Este es un correo de prueba.</strong> No se inició ningún proceso real de fax.</p>
@endif

<p>El fax está adjunto como archivo {{ ($attributes['attachment_mime'] ?? '') === 'application/pdf' ? 'PDF' : 'TIFF' }}.</p>

<p>Si tienes alguna pregunta, <a href="mailto:{{ $attributes['support_email'] ?? '' }}">escribe a nuestro equipo de atención al cliente</a>.</p>
<p>Gracias,<br>El equipo de {{ config('app.name', 'Laravel') }}</p>
@endsection
