{{-- email-template
version: 1.0.0
language: pt-br
category: fax
subcategory: received
format: html
layout: standard
subject: Fax recebido para {{ $attributes['fax_destination'] }}
description: Notificação de fax recebido
--}}
@extends('emails.pt-br.email_layout')

@section('content')
<h1>Fax recebido{{ $attributes['caller_id_number'] ? ' de ' . $attributes['caller_id_number'] : '' }}.</h1>

<p>Foi recebido um novo fax para {{ $attributes['fax_destination'] }} e está anexado a este e-mail.</p>

<ul>
    {{-- <li><strong>Domain:</strong> {{ $attributes['domain_name'] ?? '' }}</li> --}}
    <li><strong>De:</strong> {{ $attributes['caller_display'] }}</li>
    <li><strong>Para:</strong> {{ $attributes['fax_destination'] }}</li>
    <li><strong>Páginas:</strong> {{ $attributes['fax_pages'] ?? '' }}</li>
    @if (!empty($attributes['fax_date']))
        <li><strong>Recebido:</strong> {{ $attributes['fax_date'] }}</li>
    @endif
    {{-- <li><strong>Status:</strong> {{ $attributes['fax_result_text'] ?? '' }}</li> --}}
</ul>

@if (!empty($attributes['is_test']))
    <p><strong>Este é um e-mail de teste.</strong> Nenhum processo real de fax foi iniciado.</p>
@endif

<p>O fax está anexado como arquivo {{ ($attributes['attachment_mime'] ?? '') === 'application/pdf' ? 'PDF' : 'TIFF' }}.</p>

<p>Se você tiver alguma dúvida, <a href="mailto:{{ $attributes['support_email'] ?? '' }}">escreva para nossa equipe de atendimento ao cliente</a>.</p>
<p>Obrigado,<br>A equipe de {{ config('app.name', 'Laravel') }}</p>
@endsection
