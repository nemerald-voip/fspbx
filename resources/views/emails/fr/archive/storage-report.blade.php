{{-- email-template
version: 1.0.0
language: fr
category: archive
subcategory: storage-report
format: html
layout: standard
subject: Rapport de stockage d’archives
description: Rapport de stockage d’archives
--}}
@extends('emails.fr.email_layout')

@section('content')
<!-- Start Content-->

<h1>Nouveau rapport de stockage d’archives</h1>
<p>Le script de transfert s’est exécuté avec succès. Le rapport figure ci-dessous.</p>
<!-- Action -->

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>Serveur :</strong>
            {{ $attributes['hostname'] ?? 'inconnu' }}
          </td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>Succès :</strong>
            @if (isset($attributes['success'])) {{ count($attributes['success'])}} @else 0 @endif
          </td>
        </tr>
        @if (isset($attributes['failed']) && count($attributes['failed']) > 0)
        <tr>
          <td class="attributes_item"><strong>Échoué :</strong>
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
    <li>{{ $rec['name'] }} — Motif : {{ $rec['msg'] }}</li>
  @endforeach

@endif

@endsection
