{{-- email-template
version: 1.0.0
language: es-419
category: authentication
subcategory: reset-password
format: html
layout: standard
subject: Restablecer contraseña {{ config('app.name', 'FS PBX') }}
description: Restablecer contraseña
--}}
@extends('emails.es-419.email_layout')

@section('content')
<!-- Start Content-->

<p>Hola{{ isset($attributes['name']) ? ' ' . $attributes['name'] : '' }},</p>

<p>Recibes este correo porque recibimos una solicitud para restablecer la contraseña de tu cuenta de {{ config('app.name', 'Laravel') }}.</p>

<p><a href="{{ $attributes['url'] ?? '' }}">Restablecer contraseña</a></p>

<p>Este enlace para restablecer la contraseña vencerá en {{ $attributes['expire_minutes'] ?? '' }} minutos.</p>

<p>Si no solicitaste restablecer tu contraseña, no necesitas hacer nada.</p>

<p>Si tienes alguna pregunta, <a href="mailto:{{ $attributes["support_email"] ?? ''}}">escribe a nuestro equipo de atención al cliente</a>.</p>

<p>Cuida tu seguridad, <br>
El equipo de {{ config('app.name', 'Laravel') }}</p>

@endsection
