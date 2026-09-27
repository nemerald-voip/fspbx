{{-- email-template
format: text
layout: none
--}}
Добро пожаловать, {{ $attributes['recipient_name'] }}!

Мы настроили для вас внутренний номер {{ $attributes['extension'] }}. Сохраните это письмо: в нём указаны данные вашего телефона и голосовой почты.

Внутренний номер: {{ $attributes['extension'] }}
@if (!empty($attributes['direct_numbers']))
Прямые номера: {{ implode(', ', $attributes['direct_numbers']) }}
@endif
Ящик голосовой почты: {{ $attributes['voicemail_id'] }}
PIN-код голосовой почты: {{ $attributes['voicemail_pin'] }}

НАСТРОЙТЕ ПРИВЕТСТВИЕ ГОЛОСОВОЙ ПОЧТЫ

1. Наберите *97 на своём телефоне.
2. Введите PIN-код голосовой почты и нажмите #.
3. Нажмите 5 для перехода к настройкам почтового ящика.
4. Нажмите 1, чтобы записать приветствие на случай недоступности.

@if (!empty($attributes['help_url']))
Справка: {{ $attributes['help_url'] }}
@endif
@if (!empty($attributes['support_email']))
Есть вопросы? Напишите на {{ $attributes['support_email'] }}.
@endif

Добро пожаловать,
{{ $attributes['app_name'] }}
