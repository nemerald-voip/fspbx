{{-- email-template
version: 1.0.0
language: pt-br
category: authentication
subcategory: verification-code
format: html
layout: standard
subject: Código de verificação {{ config('app.name', 'FS PBX') }}
description: Código de verificação
--}}
@extends('emails.pt-br.email_layout')

@section('content')
<!-- Start Content-->

<p>Olá{{ isset($attributes['name']) ? ' ' . $attributes['name'] : '' }},</p>
<p>Use o código abaixo para concluir sua autenticação:</p>
<p>Seu código de autenticação de dois fatores: <b>{{ $attributes['code'] ?? '' }}</b></p>


<p>Use o código acima para entrar na sua conta de {{ config('app.name', 'Laravel') }}.
    Esta etapa adicional confirma que é você quem está tentando acessar sua conta.</p>


<p><b>Por que você recebeu este e-mail?</b></p>

<p>Esta medida de segurança é acionada quando a autenticação de dois fatores é ativada ou ocorre uma tentativa de entrada.
Se você iniciou esta ação, use o código para continuar. Se não solicitou este código,
nenhuma ação é necessária: sem o código, o acesso à sua conta continua protegido.</p>

<p><b>Você não solicitou este código?</b></p>

<p>Se você não solicitou este código ou suspeita de atividade não autorizada, proteja
    sua conta imediatamente, alterando sua senha e entrando em contato com nossa equipe de suporte.</p>

<p>Se você tiver alguma dúvida, <a href="mailto:{{ $attributes["support_email"] ?? ''}}">escreva para nossa equipe de atendimento ao cliente</a>.</p>

<p>Cuide da sua segurança, <br>
A equipe de {{ config('app.name', 'Laravel') }}</p>

<p><strong>P.S.</strong> Precisa de ajuda para começar? A equipe de suporte de {{ config('app.name', 'Laravel') }} está sempre pronta para ajudar! Basta responder a este e-mail.</p>

@endsection
