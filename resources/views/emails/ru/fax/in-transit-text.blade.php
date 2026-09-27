{{-- email-template
format: text
layout: none
--}}
Ваши файлы отправляются факсом на номер {{ $attributes['fax_destination'] }}.

Результат передачи будет отправлен отдельным уведомлением.

Если у вас есть вопросы, напишите нашей службе поддержки: {{ $attributes['support_email'] ?? '' }}.

Спасибо,
Команда {{ config('app.name', 'Laravel') }}

P.S. Нужна срочная помощь? Откройте {{ $attributes['help_url'] ?? '' }} или ответьте на это письмо.
