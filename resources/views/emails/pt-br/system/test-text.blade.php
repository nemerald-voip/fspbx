{{-- email-template
format: text
layout: none
--}}
Olá,

Este é um e-mail de teste de {{ config('app.name', 'FS PBX') }}.

Se você recebeu esta mensagem, o serviço de e-mail configurado consegue enviar mensagens.

Enviado em {{ $attributes['sent_at'] ?? now()->toDateTimeString() }}.
