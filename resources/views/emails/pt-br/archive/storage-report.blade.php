{{-- email-template
version: 1.0.0
language: pt-br
category: archive
subcategory: storage-report
format: html
layout: standard
subject: Relatório de armazenamento de arquivos
description: Relatório de armazenamento de arquivos
--}}
@extends('emails.pt-br.email_layout')

@section('content')
<!-- Start Content-->

<h1>Novo relatório de armazenamento de arquivos</h1>
<p>O script de transferência foi executado com sucesso. O relatório está abaixo.</p>
<!-- Action -->

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>Servidor:</strong>
            {{ $attributes['hostname'] ?? 'desconhecido' }}
          </td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>Sucesso:</strong>
            @if (isset($attributes['success'])) {{ count($attributes['success'])}} @else 0 @endif
          </td>
        </tr>
        @if (isset($attributes['failed']) && count($attributes['failed']) > 0)
        <tr>
          <td class="attributes_item"><strong>Falhou:</strong>
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
