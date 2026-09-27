{{-- email-template
version: 1.0.0
language: pt-br
category: authentication
subcategory: reset-password
format: html
layout: standard
subject: Redefinição de senha {{ config('app.name', 'FS PBX') }}
description: Redefinição de senha
--}}
@extends('emails.pt-br.email_layout')

@section('content')
<!-- Start Content-->

<p>Olá{{ isset($attributes['name']) ? ' ' . $attributes['name'] : '' }},</p>

<p>Você recebeu este e-mail porque recebemos uma solicitação para redefinir a senha da sua conta de {{ config('app.name', 'Laravel') }}.</p>

<p><a href="{{ $attributes['url'] ?? '' }}">Redefinir Senha</a></p>

<p>Este link de redefinição de senha expirará em {{ $attributes['expire_minutes'] ?? '' }} minutos.</p>

<p>Se você não solicitou a redefinição da senha, nenhuma ação é necessária.</p>

<p>Se você tiver alguma dúvida, <a href="mailto:{{ $attributes["support_email"] ?? ''}}">escreva para nossa equipe de atendimento ao cliente</a>.</p>

<p>Cuide da sua segurança, <br>
A equipe de {{ config('app.name', 'Laravel') }}</p>

@endsection
