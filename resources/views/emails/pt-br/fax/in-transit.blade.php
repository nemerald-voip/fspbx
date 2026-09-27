{{-- email-template
version: 1.0.0
language: pt-br
category: fax
subcategory: in-transit
format: html
layout: standard
subject: Enviando fax para {{ $fax_destination }}
description: Notificação de aceitação de fax de saída
--}}
@extends('emails.pt-br.email_layout')

@section('content')
<!-- Start Content-->

<h1>Seus arquivos estão sendo enviados por fax para {{ $attributes['fax_destination'] }}.</h1>
<p>Você receberá outras notificações sobre o resultado da transmissão.</p>
<!-- Action -->

<p>Se você tiver alguma dúvida, <a href="mailto:{{ $attributes["support_email"] ?? ''}}">escreva para nossa equipe de atendimento ao cliente</a>. (Respondemos rapidamente.)</p>
<p>Obrigado,
  <br>A equipe de {{ config('app.name', 'Laravel') }}</p>
<p><strong>P.S.</strong> Precisa de ajuda para começar? A equipe de suporte de {{ config('app.name', 'Laravel') }} está sempre pronta para ajudar! Consulte nossa <a href="{{ $attributes["help_url"] ?? ''}}">documentação de ajuda</a>. Ou basta responder a este e-mail.</p>


@endsection
