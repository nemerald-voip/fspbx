{{-- email-template
format: text
layout: none
--}}
Seus arquivos estão sendo enviados por fax para {{ $attributes['fax_destination'] }}.

Você receberá outras notificações sobre o resultado da transmissão.

Se você tiver alguma dúvida, escreva para nossa equipe de atendimento ao cliente em {{ $attributes['support_email'] ?? '' }}.

Obrigado,
A equipe de {{ config('app.name', 'Laravel') }}

P.S. Precisa de ajuda imediata? Acesse {{ $attributes['help_url'] ?? '' }} ou responda a este e-mail.
