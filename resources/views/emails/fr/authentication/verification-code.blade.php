{{-- email-template
version: 1.0.0
language: fr
category: authentication
subcategory: verification-code
format: html
layout: standard
subject: Code de vérification {{ config('app.name', 'FS PBX') }}
description: Code de vérification
--}}
@extends('emails.fr.email_layout')

@section('content')
<!-- Start Content-->

<p>Bonjour{{ isset($attributes['name']) ? ' ' . $attributes['name'] : '' }},</p>
<p>Utilisez le code ci-dessous pour terminer votre authentification :</p>
<p>Votre code de double authentification : <b>{{ $attributes['code'] ?? '' }}</b></p>


<p>Utilisez le code ci-dessus pour vous connecter à votre compte {{ config('app.name', 'Laravel') }}.
    Cette étape supplémentaire confirme que c’est bien vous qui tentez d’accéder à votre compte.</p>


<p><b>Pourquoi recevez-vous cet e-mail ?</b></p>

<p>Cette mesure de sécurité s’applique lorsque l’authentification à deux facteurs est activée ou lors d’une tentative de connexion.
Si vous êtes à l’origine de cette action, utilisez le code pour continuer. Sinon,
aucune action n’est nécessaire : sans le code, l’accès à votre compte reste protégé.</p>

<p><b>Vous n’avez pas demandé ce code ?</b></p>

<p>Si vous n’avez pas demandé ce code ou soupçonnez une activité non autorisée, sécurisez
    immédiatement votre compte en changeant votre mot de passe et en contactant notre équipe d’assistance.</p>

<p>Si vous avez des questions, <a href="mailto:{{ $attributes["support_email"] ?? ''}}">contactez notre équipe d’assistance</a>.</p>

<p>Prenez soin de votre sécurité, <br>
L’équipe {{ config('app.name', 'Laravel') }}</p>

<p><strong>P.-S.</strong> Besoin d’aide pour démarrer ? L’équipe d’assistance de {{ config('app.name', 'Laravel') }} est toujours prête à vous aider ! Répondez simplement à cet e-mail.</p>

@endsection
