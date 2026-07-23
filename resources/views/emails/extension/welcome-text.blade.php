{{-- email-template
format: text
layout: none
--}}
Welcome, {{ $attributes['recipient_name'] }}!

We've set up extension {{ $attributes['extension'] }} for you. Keep this email handy for your phone and voicemail details.

Extension: {{ $attributes['extension'] }}
@if (!empty($attributes['direct_numbers']))
Direct number{{ count($attributes['direct_numbers']) === 1 ? '' : 's' }}: {{ implode(', ', $attributes['direct_numbers']) }}
@endif
Voicemail mailbox: {{ $attributes['voicemail_id'] }}
Voicemail PIN: {{ $attributes['voicemail_pin'] }}

SET UP YOUR VOICEMAIL GREETING

1. Dial *97 from your phone.
2. Enter your voicemail PIN, then press #.
3. Press 5 for mailbox options.
4. Press 1 to record your unavailable greeting.

@if (!empty($attributes['help_url']))
Help: {{ $attributes['help_url'] }}
@endif
@if (!empty($attributes['support_email']))
Questions? Email {{ $attributes['support_email'] }}.
@endif

Welcome aboard,
{{ $attributes['app_name'] }}
