{{-- email-template
format: text
layout: none
--}}
Здравствуйте,

Это тестовое письмо от {{ config('app.name', 'FS PBX') }}.

Если вы получили это сообщение, настроенная почтовая служба может отправлять письма.

Отправлено {{ $attributes['sent_at'] ?? now()->toDateTimeString() }}.
