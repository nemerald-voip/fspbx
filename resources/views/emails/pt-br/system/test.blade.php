{{-- email-template
version: 1.0.0
language: pt-br
category: system
subcategory: test
format: html
layout: standard
subject: E-mail de teste de {{ config('app.name', 'FS PBX') }}
description: Teste de entrega de e-mail
--}}
@extends('emails.pt-br.email_layout')

@section('content')
<p>Olá,</p>

<p>Este é um e-mail de teste de {{ config('app.name', 'FS PBX') }}.</p>

<p>Se você recebeu esta mensagem, o serviço de e-mail configurado consegue enviar mensagens.</p>

<p>Enviado em {{ $attributes['sent_at'] ?? now()->toDateTimeString() }}.</p>
@endsection
