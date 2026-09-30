{{-- email-template
format: text
layout: none
--}}
Você tem uma nova mensagem de voz:

De: {{ $attributes['caller_id_name'] }} {{ $attributes['caller_id_number'] }}
Para a caixa postal: {{ $attributes['dialed_user'] }}
Recebido: {{ $attributes['message_date'] }}
Duração: {{ $attributes['message_duration'] }}

@if($attributes['voicemail_file_mode'] === 'attach')
Ouça esta mensagem de voz pelo telefone ou abra o arquivo de áudio anexo. Você também pode entrar na sua conta para ouvir e gerenciar suas mensagens de voz.
@elseif($attributes['voicemail_file_mode'] === 'link' && !empty($attributes['voicemail_download_url']))
Ouça esta mensagem de voz pelo telefone ou baixe a gravação em {{ $attributes['voicemail_download_url'] }}. Você também pode entrar na sua conta para ouvir e gerenciar suas mensagens de voz.
@else
Ouça esta mensagem de voz pelo telefone. Você também pode entrar na sua conta para ouvir e gerenciar suas mensagens de voz.
@endif
