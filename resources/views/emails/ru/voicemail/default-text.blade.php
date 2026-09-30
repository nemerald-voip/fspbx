{{-- email-template
format: text
layout: none
--}}
У вас новое голосовое сообщение:

От: {{ $attributes['caller_id_name'] }} {{ $attributes['caller_id_number'] }}
В почтовый ящик: {{ $attributes['dialed_user'] }}
Получено: {{ $attributes['message_date'] }}
Длительность: {{ $attributes['message_duration'] }}

@if($attributes['voicemail_file_mode'] === 'attach')
Прослушайте голосовое сообщение по телефону или откройте вложенный аудиофайл. Вы также можете войти в аккаунт, чтобы прослушивать голосовые сообщения и управлять ими.
@elseif($attributes['voicemail_file_mode'] === 'link' && !empty($attributes['voicemail_download_url']))
Прослушайте голосовое сообщение по телефону или скачайте запись по адресу {{ $attributes['voicemail_download_url'] }}. Вы также можете войти в аккаунт, чтобы прослушивать голосовые сообщения и управлять ими.
@else
Прослушайте голосовое сообщение по телефону. Вы также можете войти в аккаунт, чтобы прослушивать голосовые сообщения и управлять ими.
@endif
