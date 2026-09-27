{{-- email-template
version: 1.0.0
language: fr
category: system
subcategory: test
format: html
layout: standard
subject: E-mail de test de {{ config('app.name', 'FS PBX') }}
description: Test de distribution des e-mails
--}}
@extends('emails.fr.email_layout')

@section('content')
<p>Bonjour,</p>

<p>Ceci est un e-mail de test de {{ config('app.name', 'FS PBX') }}.</p>

<p>Si vous avez reçu ce message, le service de messagerie configuré peut envoyer des e-mails.</p>

<p>Envoyé le {{ $attributes['sent_at'] ?? now()->toDateTimeString() }}.</p>
@endsection
