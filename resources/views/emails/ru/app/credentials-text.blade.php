{{-- email-template
format: text
layout: none
--}}
Добро пожаловать в приложение {{ config('app.name', 'Laravel') }}. Сохраните это письмо для дальнейшего использования. Ниже приведены простые шаги для начала работы:

Скачайте приложение для своих устройств:

Google Play: {{ $attributes['google_play_link'] ?? '' }}
Apple Store: {{ $attributes['apple_store_link'] ?? '' }}
Скачать для Windows ({{ $attributes['windows_link'] ?? '' }})
Скачать для Mac ({{ $attributes['mac_link'] ?? '' }})

Отображаемое имя: {{ $attributes['name'] ?? ''}}
Внутренний номер АТС: {{ $attributes['extension'] ?? ''}}

Используйте эти данные для входа:

Домен: {{ $attributes['domain'] ?? ''}}
Имя пользователя: {{ $attributes['username'] ?? ''}}
@if(!empty($attributes['password_url']))
Пароль: {{ $attributes['password_url'] }}
@elseif(!empty($attributes['password']))
Пароль: {{ $attributes['password'] }}
@endif

После входа вы сможете общаться с сотрудниками своей организации: совершать и принимать звонки через свой внутренний номер, ставить вызовы на удержание, переводить и парковать их и многое другое.

Если у вас есть вопросы, напишите нашей службе поддержки: {{ $attributes['support_email'] ?? '' }}. (Мы отвечаем очень быстро.)

Спасибо,
Команда {{ config('app.name', 'Laravel') }}

P.S. Нужна помощь с началом работы? Служба поддержки {{ config('app.name', 'Laravel') }} всегда готова помочь! Просто ответьте на это письмо.
