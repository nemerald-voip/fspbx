{{-- email-template
version: 1.0.0
language: pt-br
category: export
subcategory: completed
format: html
layout: standard
subject: Seu relatório está pronto
description: Seu relatório está pronto
--}}
@extends('emails.pt-br.email_layout')

@section('content')
<!-- Start Content-->

<h1>Seu relatório está pronto.</h1>

<p>O arquivo CSV solicitado está pronto para download.</p>


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
            Baixar relatório
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

<p>Obrigado!</p>

@endsection
