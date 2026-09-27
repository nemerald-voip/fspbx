{{-- email-template
version: 1.0.0
language: pt-br
category: extension
subcategory: welcome
format: html
layout: standard
subject: Seu ramal {{ $attributes['extension'] }} está pronto
description: Informações do ramal e do correio de voz
--}}
@extends('emails.pt-br.email_layout')

@section('content')
<h1>Boas-vindas, {{ $attributes['recipient_name'] }}!</h1>

<p>Configuramos o ramal <strong>{{ $attributes['extension'] }}</strong> para você. Guarde este e-mail para consultar os dados do telefone e do correio de voz.</p>

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>Ramal:</strong> {{ $attributes['extension'] }}</td>
        </tr>
        @if (!empty($attributes['direct_numbers']))
          <tr>
            <td class="attributes_item"><strong>Números diretos:</strong> {{ implode(', ', $attributes['direct_numbers']) }}</td>
          </tr>
        @endif
        <tr>
          <td class="attributes_item"><strong>Caixa postal de voz:</strong> {{ $attributes['voicemail_id'] }}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>PIN do Correio de Voz:</strong> {{ $attributes['voicemail_pin'] }}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<h2>Configure sua saudação do correio de voz</h2>
<ol>
  <li>Disque <strong>*97</strong> no seu telefone.</li>
  <li>Digite o PIN do correio de voz e pressione <strong>#</strong>.</li>
  <li>Pressione <strong>5</strong> para acessar as opções da caixa postal.</li>
  <li>Pressione <strong>1</strong> para gravar sua saudação de indisponibilidade.</li>
</ol>

@if (!empty($attributes['help_url']))
  <p>Você encontra mais ajuda na <a href="{{ $attributes['help_url'] }}">central de ajuda</a>.</p>
@endif

@if (!empty($attributes['support_email']))
  <p>Dúvidas? Escreva para <a href="mailto:{{ $attributes['support_email'] }}">{{ $attributes['support_email'] }}</a>.</p>
@endif

<p>Seja bem-vindo,<br>{{ $attributes['app_name'] }}</p>
@endsection
