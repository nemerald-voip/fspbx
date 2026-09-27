{{-- email-template
version: 1.0.0
language: es-419
category: export
subcategory: completed
format: html
layout: standard
subject: Tu informe está listo
description: Tu informe está listo
--}}
@extends('emails.es-419.email_layout')

@section('content')
<!-- Start Content-->

<h1>Tu informe está listo.</h1>

<p>El archivo CSV que solicitaste está listo para descargar.</p>


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
            Descargar informe
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

<p>¡Gracias!</p>

@endsection
