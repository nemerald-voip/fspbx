{{-- email-template
version: 1.0.0
language: fr
category: fax
subcategory: service-alert
format: html
layout: standard
subject: Alerte du service de fax
description: Alerte du service de fax
--}}
@extends('emails.fr.email_layout')

@section('content')
<!-- Start Content-->

<h1>Alerte du service de fax</h1>

@if(isset($attributes["pendingFaxes"]))
    <p>{{ $attributes["pendingFaxes"] }} fax sortants sont en attente depuis plus de {{ $attributes["waitTimeThreshold"] }} minutes. Vérifiez l’état du service de fax.</p>
@endif

@if(isset($attributes["failedFaxes"]))
    <p>{{ $attributes["failedFaxes"] }} sur {{ $attributes["totalChecked"] }} fax récemment traités ont échoué ({{ $attributes["failureRate"] }}% d’échecs).</p>
    <p>Cela peut indiquer un problème avec le service de fax.</p>
@endif

<!-- Action -->
<p>Merci,<br>L’équipe {{ config('app.name', 'Laravel') }}</p>

@endsection
