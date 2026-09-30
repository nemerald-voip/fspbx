{{-- email-template
version: 1.0.0
language: fr
category: missed
subcategory: ring-group
format: html
layout: standard
subject: Appel manqué vers {{ $attributes['ring_group_display'] }}
description: Notification d’appel manqué vers un groupe de sonnerie
--}}
@extends('emails.fr.email_layout')

@section('content')
<h1>Appel manqué{{ $attributes['caller_id_number'] ? ' de ' . $attributes['caller_id_number'] : '' }}.</h1>

<p>Un appel vers {{ $attributes['ring_group_display'] ?: 'votre groupe de sonnerie' }} est resté sans réponse.</p>

<ul>
    <li><strong>De :</strong> {{ $attributes['caller_display'] ?: 'Appelant inconnu' }}</li>
    <li><strong>À :</strong> {{ $attributes['ring_group_display'] ?: 'Groupe de sonnerie' }}</li>
    @if (!empty($attributes['destination_number']))
        <li><strong>Numéro composé :</strong> {{ $attributes['destination_number'] }}</li>
    @endif
</ul>

<p>Merci,<br>L’équipe {{ config('app.name', 'FS PBX') }}</p>
@endsection
