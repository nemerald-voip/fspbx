{{-- email-template
version: 1.0.0
language: fr
category: authentication
subcategory: reset-password
format: html
layout: standard
subject: Réinitialisation du mot de passe {{ config('app.name', 'FS PBX') }}
description: Réinitialisation du mot de passe
--}}
@extends('emails.fr.email_layout')

@section('content')
<!-- Start Content-->

<p>Bonjour{{ isset($attributes['name']) ? ' ' . $attributes['name'] : '' }},</p>

<p>Vous recevez cet e-mail car nous avons reçu une demande de réinitialisation du mot de passe de votre compte {{ config('app.name', 'Laravel') }}.</p>

<p><a href="{{ $attributes['url'] ?? '' }}">Réinitialiser le mot de passe</a></p>

<p>Ce lien de réinitialisation expirera dans {{ $attributes['expire_minutes'] ?? '' }} minutes.</p>

<p>Si vous n’avez pas demandé de réinitialisation, aucune action n’est nécessaire.</p>

<p>Si vous avez des questions, <a href="mailto:{{ $attributes["support_email"] ?? ''}}">contactez notre équipe d’assistance</a>.</p>

<p>Prenez soin de votre sécurité, <br>
L’équipe {{ config('app.name', 'Laravel') }}</p>

@endsection
