{{-- email-template
version: 1.0.0
language: fr
category: fax
subcategory: sent
format: html
layout: standard
subject: Fax livré au {{ $attributes['fax_destination'] }}
description: Notification de livraison d’un fax
--}}
@extends('emails.fr.email_layout')

@section('content')
<!-- Start Content-->

<h1>Votre fax au {{ $attributes['fax_destination'] }} a été livré.</h1>

<p>Le fax a été transmis avec succès le
{{ $attributes['fax_date'] ?? now()->format('Y-m-d H:i') }}.</p>

@if (!empty($attributes['fax_pages']))
    <p>Pages envoyées : <strong>{{ $attributes['fax_pages'] }}</strong>{!! isset($attributes['fax_total_pages']) && $attributes['fax_total_pages'] !== $attributes['fax_pages'] ? ' sur ' . $attributes['fax_total_pages'] : '' !!}.</p>
@endif

@if (!empty($attributes['fax_duration_formatted']))
    <p>Durée : {{ $attributes['fax_duration_formatted'] }}.</p>
@endif

<p>Le fax transmis est joint à cet e-mail pour vos archives.</p>

<p>Merci,<br>L’équipe {{ config('app.name', 'Laravel') }}</p>

@endsection
