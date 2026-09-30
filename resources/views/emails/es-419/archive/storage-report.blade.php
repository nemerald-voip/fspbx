{{-- email-template
version: 1.0.0
language: es-419
category: archive
subcategory: storage-report
format: html
layout: standard
subject: Informe de almacenamiento de archivos
description: Informe de almacenamiento de archivos
--}}
@extends('emails.es-419.email_layout')

@section('content')
<!-- Start Content-->

<h1>Nuevo informe de almacenamiento de archivos</h1>
<p>El script de transferencia se ejecutó correctamente. El informe está a continuación.</p>
<!-- Action -->

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>Servidor:</strong>
            {{ $attributes['hostname'] ?? 'desconocido' }}
          </td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>Éxito:</strong>
            @if (isset($attributes['success'])) {{ count($attributes['success'])}} @else 0 @endif
          </td>
        </tr>
        @if (isset($attributes['failed']) && count($attributes['failed']) > 0)
        <tr>
          <td class="attributes_item"><strong>Falló:</strong>
              {{ count($attributes['failed'])}}
            </td>
        </tr>
        @endif
      </table>
    </td>
  </tr>
</table>

@if (isset($attributes['failed']) && count($attributes['failed']) > 0)

  @foreach ($attributes['failed'] as $rec)
    <li>{{ $rec['name'] }} — Motivo: {{ $rec['msg'] }}</li>
  @endforeach

@endif

@endsection
