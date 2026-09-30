{{-- email-template
version: 1.0.0
language: es-419
category: fax
subcategory: invalid-destination
format: html
layout: standard
subject: Error de fax: número no válido {{ $invalid_number }}
description: Error de fax: número no válido
--}}
@extends('emails.es-419.email_layout')

@section('content')
<!-- Start Content-->

<h1>El envío de tu fax a {{ $attributes['invalid_number'] }} falló</h1>
<p>El número de destino no es un número de teléfono válido de Estados Unidos.</p>
<!-- Action -->

@endsection
