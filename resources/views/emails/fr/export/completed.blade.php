{{-- email-template
version: 1.0.0
language: fr
category: export
subcategory: completed
format: html
layout: standard
subject: Votre rapport est prêt
description: Votre rapport est prêt
--}}
@extends('emails.fr.email_layout')

@section('content')
<!-- Start Content-->

<h1>Votre rapport est prêt.</h1>

<p>Le fichier CSV demandé est prêt à être téléchargé.</p>


<table class="action" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
    <td align="center">
    <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
    <td align="center">
    <table border="0" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
    <td>
        <a href="{{ $attributes['fileUrl'] }}" target="_blank" rel="noopener" style="display: inline-block; padding: 10px 20px; font-size: 16px; color: #ffffff; background-color: #4a90e2; border-radius: 5px; text-decoration: none;">
            Télécharger le rapport
        </a>
    </td>
    </tr>
    </table>
    </td>
    </tr>
    </table>
    </td>
    </tr>
    </table>

<p>Merci !</p>

@endsection
