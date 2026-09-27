{{-- email-template
format: text
layout: none
--}}
Olá{{ isset($attributes['name']) ? ' ' . $attributes['name'] : '' }},

Use o código abaixo para concluir sua autenticação:

Seu código de autenticação de dois fatores: {{ $attributes['code'] ?? '' }}

Use o código acima para entrar na sua conta de {{ config('app.name', 'Laravel') }}.
Esta etapa adicional confirma que é você quem está tentando acessar sua conta.

Por que você recebeu este e-mail?
Esta medida de segurança é acionada quando a autenticação de dois fatores é ativada ou ocorre uma tentativa de entrada.
Se você iniciou esta ação, use o código para continuar. Se não solicitou este código,
nenhuma ação é necessária: sem o código, o acesso à sua conta continua protegido.

Você não solicitou este código?
Se você não solicitou este código ou suspeita de atividade não autorizada, proteja
sua conta imediatamente, alterando sua senha e entrando em contato com nossa equipe de suporte.

Se você tiver alguma dúvida, escreva para nossa equipe de atendimento ao cliente em {{ $attributes['support_email'] ?? '' }}.

Cuide da sua segurança,
A equipe de {{ config('app.name', 'Laravel') }}

P.S. Precisa de ajuda para começar? A equipe de suporte de {{ config('app.name', 'Laravel') }} está sempre pronta para ajudar! Basta responder a este e-mail.

Não responda a este e-mail: ele foi gerado automaticamente e as respostas não são monitoradas.
