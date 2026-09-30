{{-- email-template
version: 1.0.0
language: pt-br
category: fax
subcategory: service-alert
format: html
layout: standard
subject: Alerta do serviço de fax
description: Alerta do serviço de fax
--}}
@extends('emails.pt-br.email_layout')

@section('content')
<!-- Start Content-->

<h1>Alerta do serviço de fax</h1>

@if(isset($attributes["pendingFaxes"]))
    <p>{{ $attributes["pendingFaxes"] }} faxes de saída estão pendentes há mais de {{ $attributes["waitTimeThreshold"] }} minutos. Verifique o status do serviço de fax.</p>
@endif

@if(isset($attributes["failedFaxes"]))
    <p>{{ $attributes["failedFaxes"] }} de {{ $attributes["totalChecked"] }} faxes processados recentemente falharam ({{ $attributes["failureRate"] }}% de falhas).</p>
    <p>Isso pode indicar um problema com o serviço de fax.</p>
@endif

<!-- Action -->
<p>Obrigado,<br>A equipe de {{ config('app.name', 'Laravel') }}</p>

@endsection
