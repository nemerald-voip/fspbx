{{-- email-template
version: 1.0.0
language: fr
category: fax
subcategory: invalid-destination
format: html
layout: standard
subject: Échec du fax : numéro incorrect {{ $invalid_number }}
description: Échec du fax : numéro incorrect
--}}
@extends('emails.fr.email_layout')

@section('content')
<!-- Start Content-->

<h1>L’envoi de votre fax au {{ $attributes['invalid_number'] }} a échoué</h1>
<p>Le numéro de destination n’est pas un numéro de téléphone américain valide.</p>
<!-- Action -->

@endsection
