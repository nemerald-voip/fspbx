{{-- email-template
format: text
layout: none
--}}
Olá{{ isset($attributes['name']) ? ' ' . $attributes['name'] : '' }},

Você recebeu este e-mail porque recebemos uma solicitação para redefinir a senha da sua conta de {{ config('app.name', 'Laravel') }}.

Redefina sua senha: {{ $attributes['url'] ?? '' }}

Este link de redefinição de senha expirará em {{ $attributes['expire_minutes'] ?? '' }} minutos.

Se você não solicitou a redefinição da senha, nenhuma ação é necessária.

Se você tiver alguma dúvida, escreva para nossa equipe de atendimento ao cliente em {{ $attributes['support_email'] ?? '' }}.

Cuide da sua segurança,
A equipe de {{ config('app.name', 'Laravel') }}

Não responda a este e-mail: ele foi gerado automaticamente e as respostas não são monitoradas.
