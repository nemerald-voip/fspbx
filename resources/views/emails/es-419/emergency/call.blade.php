{{-- email-template
version: 1.0.0
language: es-419
category: emergency
subcategory: call
format: html
layout: standard
subject: Llamada de emergencia desde {{ $attributes['caller'] }}
description: Notificación de llamada de emergencia
--}}
@extends('emails.es-419.email_layout')

@section('content')
<!-- Start Content -->

<h1>Notificación de llamada de emergencia</h1>

<p>Se realizó una llamada de emergencia desde la extensión <strong>{{ $attributes['caller'] }}</strong>.</p>

<p>Toma las medidas necesarias de inmediato.</p>

<p>Gracias y cuídate.</p>

@endsection
