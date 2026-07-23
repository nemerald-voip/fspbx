{{-- email-template
format: text
layout: none
--}}
You have a new voice message:

From: {{ $attributes['caller_id_name'] }} {{ $attributes['caller_id_number'] }}
To mailbox: {{ $attributes['dialed_user'] }}
Received: {{ $attributes['message_date'] }}
Length: {{ $attributes['message_duration'] }}

@if($attributes['voicemail_file_mode'] === 'attach')
Listen to this voicemail over your phone or by opening the attached sound file. You can also sign in to your account with your credentials to manage and listen to voicemails.
@elseif($attributes['voicemail_file_mode'] === 'link' && !empty($attributes['voicemail_download_url']))
Listen to this voicemail over your phone or download the recording at {{ $attributes['voicemail_download_url'] }}. You can also sign in to your account with your credentials to manage and listen to voicemails.
@else
Listen to this voicemail over your phone. You can also sign in to your account with your credentials to manage and listen to voicemails.
@endif
