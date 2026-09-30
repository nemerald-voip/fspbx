{{-- email-template
version: 1.0.0
language: es-419
category: fax
subcategory: in-transit
format: html
layout: standard
subject: Enviando fax a {{ $fax_destination }}
description: Notificación de aceptación de fax saliente
--}}
@extends('emails.es-419.email_layout')

@section('content')
<!-- Start Content-->

<h1>Tus archivos se están enviando por fax a {{ $attributes['fax_destination'] }}.</h1>
<p>Recibirás más notificaciones sobre el resultado de la transmisión.</p>
<!-- Action -->

<p>Si tienes alguna pregunta, <a href="mailto:{{ $attributes["support_email"] ?? ''}}">escribe a nuestro equipo de atención al cliente</a>. (Respondemos muy rápido.)</p>
<p>Gracias,
  <br>El equipo de {{ config('app.name', 'Laravel') }}</p>
<p><strong>P. D.</strong> ¿Necesitas ayuda para comenzar? El equipo de soporte de {{ config('app.name', 'Laravel') }} siempre está listo para ayudarte. Consulta nuestra <a href="{{ $attributes["help_url"] ?? ''}}">documentación de ayuda</a>. O simplemente responde a este correo.</p>


@endsection
