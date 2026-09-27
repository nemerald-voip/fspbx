{{-- email-template
version: 1.0.0
language: es-419
category: fax
subcategory: service-alert
format: html
layout: standard
subject: Alerta del servicio de fax
description: Alerta del servicio de fax
--}}
@extends('emails.es-419.email_layout')

@section('content')
<!-- Start Content-->

<h1>Alerta del servicio de fax</h1>

@if(isset($attributes["pendingFaxes"]))
    <p>{{ $attributes["pendingFaxes"] }} faxes salientes llevan pendientes más de {{ $attributes["waitTimeThreshold"] }} minutos. Revisa el estado del servicio de fax.</p>
@endif

@if(isset($attributes["failedFaxes"]))
    <p>{{ $attributes["failedFaxes"] }} de {{ $attributes["totalChecked"] }} faxes procesados recientemente fallaron ({{ $attributes["failureRate"] }}% de fallas).</p>
    <p>Esto puede indicar un problema con el servicio de fax.</p>
@endif

<!-- Action -->
<p>Gracias,<br>El equipo de {{ config('app.name', 'Laravel') }}</p>

@endsection
