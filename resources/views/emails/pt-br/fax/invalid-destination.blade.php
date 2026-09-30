{{-- email-template
version: 1.0.0
language: pt-br
category: fax
subcategory: invalid-destination
format: html
layout: standard
subject: Falha no fax: número inválido {{ $invalid_number }}
description: Falha no fax: número inválido
--}}
@extends('emails.pt-br.email_layout')

@section('content')
<!-- Start Content-->

<h1>O envio do seu fax para {{ $attributes['invalid_number'] }} falhou</h1>
<p>O número de destino não é um número de telefone válido dos Estados Unidos.</p>
<!-- Action -->

@endsection
