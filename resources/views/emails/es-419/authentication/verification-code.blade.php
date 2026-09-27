{{-- email-template
version: 1.0.0
language: es-419
category: authentication
subcategory: verification-code
format: html
layout: standard
subject: Código de verificación {{ config('app.name', 'FS PBX') }}
description: Código de verificación
--}}
@extends('emails.es-419.email_layout')

@section('content')
<!-- Start Content-->

<p>Hola{{ isset($attributes['name']) ? ' ' . $attributes['name'] : '' }},</p>
<p>Usa el siguiente código para completar tu autenticación:</p>
<p>Tu código de autenticación de dos factores: <b>{{ $attributes['code'] ?? '' }}</b></p>


<p>Usa el código anterior para iniciar sesión en tu cuenta de {{ config('app.name', 'Laravel') }}.
    Este paso adicional confirma que eres tú quien intenta acceder a tu cuenta.</p>


<p><b>¿Por qué recibes este correo?</b></p>

<p>Esta medida de seguridad se activa cuando se habilita la autenticación de dos factores o se intenta iniciar sesión.
Si iniciaste esta acción, usa el código para continuar. Si no solicitaste este código,
no necesitas hacer nada: sin el código, el acceso a tu cuenta permanece protegido.</p>

<p><b>¿No solicitaste este código?</b></p>

<p>Si no solicitaste este código o sospechas de alguna actividad no autorizada, protege
    tu cuenta de inmediato cambiando tu contraseña y contactando a nuestro equipo de soporte.</p>

<p>Si tienes alguna pregunta, <a href="mailto:{{ $attributes["support_email"] ?? ''}}">escribe a nuestro equipo de atención al cliente</a>.</p>

<p>Cuida tu seguridad, <br>
El equipo de {{ config('app.name', 'Laravel') }}</p>

<p><strong>P. D.</strong> ¿Necesitas ayuda para comenzar? El equipo de soporte de {{ config('app.name', 'Laravel') }} siempre está listo para ayudarte. Solo responde a este correo.</p>

@endsection
