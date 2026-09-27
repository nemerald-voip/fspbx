{{-- email-template
version: 1.0.0
language: es-419
category: extension
subcategory: welcome
format: html
layout: standard
subject: Tu extensión {{ $attributes['extension'] }} está lista
description: Información de la extensión y del correo de voz
--}}
@extends('emails.es-419.email_layout')

@section('content')
<h1>¡Bienvenido, {{ $attributes['recipient_name'] }}!</h1>

<p>Configuramos la extensión <strong>{{ $attributes['extension'] }}</strong> para ti. Guarda este correo para consultar los datos de tu teléfono y correo de voz.</p>

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>Extensión:</strong> {{ $attributes['extension'] }}</td>
        </tr>
        @if (!empty($attributes['direct_numbers']))
          <tr>
            <td class="attributes_item"><strong>Números directos:</strong> {{ implode(', ', $attributes['direct_numbers']) }}</td>
          </tr>
        @endif
        <tr>
          <td class="attributes_item"><strong>Buzón de voz:</strong> {{ $attributes['voicemail_id'] }}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>PIN de buzón de voz:</strong> {{ $attributes['voicemail_pin'] }}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<h2>Configura tu saludo del correo de voz</h2>
<ol>
  <li>Marca <strong>*97</strong> desde tu teléfono.</li>
  <li>Ingresa el PIN de tu correo de voz y presiona <strong>#</strong>.</li>
  <li>Presiona <strong>5</strong> para acceder a las opciones del buzón.</li>
  <li>Presiona <strong>1</strong> para grabar tu saludo de no disponible.</li>
</ol>

@if (!empty($attributes['help_url']))
  <p>Puedes encontrar más ayuda en el <a href="{{ $attributes['help_url'] }}">centro de ayuda</a>.</p>
@endif

@if (!empty($attributes['support_email']))
  <p>¿Tienes preguntas? Escribe a <a href="mailto:{{ $attributes['support_email'] }}">{{ $attributes['support_email'] }}</a>.</p>
@endif

<p>Te damos la bienvenida,<br>{{ $attributes['app_name'] }}</p>
@endsection
