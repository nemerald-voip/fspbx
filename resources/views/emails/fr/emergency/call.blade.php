{{-- email-template
version: 1.0.0
language: fr
category: emergency
subcategory: call
format: html
layout: standard
subject: Appel d’urgence depuis {{ $attributes['caller'] }}
description: Notification d’appel d’urgence
--}}
@extends('emails.fr.email_layout')

@section('content')
<!-- Start Content -->

<h1>Notification d’appel d’urgence</h1>

<p>Un appel d’urgence a été passé depuis le poste <strong>{{ $attributes['caller'] }}</strong>.</p>

<p>Veuillez prendre les mesures nécessaires immédiatement.</p>

<p>Merci et prenez soin de vous.</p>

@endsection
