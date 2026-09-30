{{-- email-template
format: text
layout: none
--}}
Tienes un nuevo mensaje de voz:

De: {{ $attributes['caller_id_name'] }} {{ $attributes['caller_id_number'] }}
Para el buzón: {{ $attributes['dialed_user'] }}
Recibido: {{ $attributes['message_date'] }}
Duración: {{ $attributes['message_duration'] }}

Vista previa del mensaje de voz:
{{ $attributes['message_text'] }}

@if($attributes['voicemail_file_mode'] === 'attach')
Escucha este mensaje de voz por teléfono o abre el archivo de audio adjunto. También puedes iniciar sesión en tu cuenta para escuchar y administrar tus mensajes de voz.
@elseif($attributes['voicemail_file_mode'] === 'link' && !empty($attributes['voicemail_download_url']))
Escucha este mensaje de voz por teléfono o descarga la grabación en {{ $attributes['voicemail_download_url'] }}. También puedes iniciar sesión en tu cuenta para escuchar y administrar tus mensajes de voz.
@else
Escucha este mensaje de voz por teléfono. También puedes iniciar sesión en tu cuenta para escuchar y administrar tus mensajes de voz.
@endif

Si tienes alguna pregunta, escribe a nuestro equipo de atención al cliente. (Respondemos muy rápido.)
