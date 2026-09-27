{{-- email-template
version: 1.0.0
language: fr
category: fax
subcategory: received
format: html
layout: standard
subject: Fax reçu pour {{ $attributes['fax_destination'] }}
description: Notification de réception d’un fax
--}}
@extends('emails.fr.email_layout')

@section('content')
<h1>Fax reçu{{ $attributes['caller_id_number'] ? ' de ' . $attributes['caller_id_number'] : '' }}.</h1>

<p>Un nouveau fax a été reçu pour {{ $attributes['fax_destination'] }} et est joint à cet e-mail.</p>

<ul>
    {{-- <li><strong>Domain:</strong> {{ $attributes['domain_name'] ?? '' }}</li> --}}
    <li><strong>De :</strong> {{ $attributes['caller_display'] }}</li>
    <li><strong>À :</strong> {{ $attributes['fax_destination'] }}</li>
    <li><strong>Pages :</strong> {{ $attributes['fax_pages'] ?? '' }}</li>
    @if (!empty($attributes['fax_date']))
        <li><strong>Reçu :</strong> {{ $attributes['fax_date'] }}</li>
    @endif
    {{-- <li><strong>Status:</strong> {{ $attributes['fax_result_text'] ?? '' }}</li> --}}
</ul>

@if (!empty($attributes['is_test']))
    <p><strong>Ceci est un e-mail de test.</strong> Aucun traitement réel de fax n’a été déclenché.</p>
@endif

<p>Le fax est joint sous forme de fichier {{ ($attributes['attachment_mime'] ?? '') === 'application/pdf' ? 'PDF' : 'TIFF' }}.</p>

<p>Si vous avez des questions, <a href="mailto:{{ $attributes['support_email'] ?? '' }}">contactez notre équipe d’assistance</a>.</p>
<p>Merci,<br>L’équipe {{ config('app.name', 'Laravel') }}</p>
@endsection
