{{-- email-template
format: text
layout: none
--}}
Boas-vindas ao seu aplicativo {{ config('app.name', 'Laravel') }}. Guarde uma cópia deste e-mail para referência futura. Veja abaixo os passos para começar a usar o aplicativo:

Baixe o aplicativo para seus dispositivos:

Google Play: {{ $attributes['google_play_link'] ?? '' }}
Apple Store: {{ $attributes['apple_store_link'] ?? '' }}
Baixar para Windows ({{ $attributes['windows_link'] ?? '' }})
Baixar para Mac ({{ $attributes['mac_link'] ?? '' }})

Nome de exibição: {{ $attributes['name'] ?? ''}}
Ramal do PBX: {{ $attributes['extension'] ?? ''}}

Use estas credenciais para entrar:

Domínio: {{ $attributes['domain'] ?? ''}}
Nome de Usuário: {{ $attributes['username'] ?? ''}}
@if(!empty($attributes['password_url']))
Senha: {{ $attributes['password_url'] }}
@elseif(!empty($attributes['password']))
Senha: {{ $attributes['password'] }}
@endif

Após entrar, você poderá se comunicar com os usuários da sua organização: fazer e receber chamadas pelo seu ramal, colocá-las em espera, transferi-las, estacioná-las e muito mais.

Se você tiver alguma dúvida, escreva para nossa equipe de atendimento ao cliente em {{ $attributes['support_email'] ?? '' }}. (Respondemos rapidamente.)

Obrigado,
A equipe de {{ config('app.name', 'Laravel') }}

P.S. Precisa de ajuda para começar? A equipe de suporte de {{ config('app.name', 'Laravel') }} está sempre pronta para ajudar! Basta responder a este e-mail.
