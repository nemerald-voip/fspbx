{{-- email-template
format: text
layout: none
--}}
Предупреждение службы факсов

@if(isset($attributes["pendingFaxes"]))
{{ $attributes["pendingFaxes"] }} исходящих факсов ожидают отправки дольше {{ $attributes["waitTimeThreshold"] }} минут. Проверьте состояние службы факсов.
@endif

@if(isset($attributes["failedFaxes"]))
{{ $attributes["failedFaxes"] }} из {{ $attributes["totalChecked"] }} недавно обработанных факсов завершились ошибкой ({{ $attributes["failureRate"] }}% неудачных отправок).
Это может указывать на проблему со службой факсов.
@endif

Спасибо,

Команда {{ config('app.name', 'Laravel') }}
