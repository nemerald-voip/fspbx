{{-- email-template
format: text
layout: none
--}}
Hello,

This is a test email from {{ config('app.name', 'FS PBX') }}.

If you received this message, the configured mail service is able to send email.

Sent at {{ $attributes['sent_at'] ?? now()->toDateTimeString() }}.
