{{-- email-template
format: text
layout: none
--}}
Vous avez un nouveau message vocal :

De : {{ $attributes['caller_id_name'] }} {{ $attributes['caller_id_number'] }}
Pour la boîte vocale : {{ $attributes['dialed_user'] }}
Reçu : {{ $attributes['message_date'] }}
Durée : {{ $attributes['message_duration'] }}

@if($attributes['voicemail_file_mode'] === 'attach')
Écoutez ce message vocal sur votre téléphone ou en ouvrant le fichier audio joint. Vous pouvez également vous connecter à votre compte pour écouter et gérer vos messages vocaux.
@elseif($attributes['voicemail_file_mode'] === 'link' && !empty($attributes['voicemail_download_url']))
Écoutez ce message vocal sur votre téléphone ou téléchargez l’enregistrement à l’adresse {{ $attributes['voicemail_download_url'] }}. Vous pouvez également vous connecter à votre compte pour écouter et gérer vos messages vocaux.
@else
Écoutez ce message vocal sur votre téléphone. Vous pouvez également vous connecter à votre compte pour écouter et gérer vos messages vocaux.
@endif
