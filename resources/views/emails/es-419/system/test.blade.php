{{-- email-template
version: 1.0.0
language: es-419
category: system
subcategory: test
format: html
layout: standard
subject: Correo de prueba de {{ config('app.name', 'FS PBX') }}
description: Prueba de entrega de correo electrónico
--}}
@extends('emails.es-419.email_layout')

@section('content')
<p>Hola,</p>

<p>Este es un correo de prueba de {{ config('app.name', 'FS PBX') }}.</p>

<p>Si recibiste este mensaje, el servicio de correo configurado puede enviar correos electrónicos.</p>

<p>Enviado el {{ $attributes['sent_at'] ?? now()->toDateTimeString() }}.</p>
@endsection
