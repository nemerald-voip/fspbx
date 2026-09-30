{{-- email-template
version: 1.0.0
language: fr
category: missed
subcategory: contact-center
format: html
layout: standard
subject: Appel abandonné dans {{ $attributes['queue_display'] }}
description: Notification d’appel abandonné au centre de contact
--}}
@extends('emails.fr.email_layout')

@section('content')
<h1>Appel abandonné{{ $attributes['caller_id_number'] ? ' de '.$attributes['caller_id_number'] : '' }}.</h1>

<p>Un appelant a quitté {{ $attributes['queue_display'] }} avant qu’un agent ne réponde.</p>

<ul>
    <li><strong>De :</strong> {{ $attributes['caller_display'] }}</li>
    <li><strong>Centre de contact :</strong> {{ $attributes['queue_display'] }}</li>
    <li><strong>Motif :</strong> {{ $attributes['departure_reason'] }}</li>
    @if (!empty($attributes['wait_duration']))
        <li><strong>Temps d’attente :</strong> {{ $attributes['wait_duration'] }}</li>
    @endif
    <li><strong>ID d'appel :</strong> {{ $attributes['call_uuid'] }}</li>
</ul>

<p>Merci,<br>L’équipe {{ config('app.name', 'FS PBX') }}</p>
@endsection
