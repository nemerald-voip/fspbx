{{-- email-template
format: text
layout: none
--}}
Boas-vindas, {{ $attributes['recipient_name'] }}!

Configuramos o ramal {{ $attributes['extension'] }} para você. Guarde este e-mail para consultar os dados do telefone e do correio de voz.

Ramal: {{ $attributes['extension'] }}
@if (!empty($attributes['direct_numbers']))
Números diretos: {{ implode(', ', $attributes['direct_numbers']) }}
@endif
Caixa postal de voz: {{ $attributes['voicemail_id'] }}
PIN do Correio de Voz: {{ $attributes['voicemail_pin'] }}

CONFIGURE SUA SAUDAÇÃO DO CORREIO DE VOZ

1. Disque *97 no seu telefone.
2. Digite o PIN do correio de voz e pressione #.
3. Pressione 5 para acessar as opções da caixa postal.
4. Pressione 1 para gravar sua saudação de indisponibilidade.

@if (!empty($attributes['help_url']))
Ajuda: {{ $attributes['help_url'] }}
@endif
@if (!empty($attributes['support_email']))
Dúvidas? Escreva para {{ $attributes['support_email'] }}.
@endif

Seja bem-vindo,
{{ $attributes['app_name'] }}
