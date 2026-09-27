{{-- email-template
format: text
layout: none
--}}
Вызов, прерванный абонентом{{ $attributes['caller_id_number'] ? ' от '.$attributes['caller_id_number'] : '' }}.

Абонент покинул {{ $attributes['queue_display'] }} до ответа оператора.

От: {{ $attributes['caller_display'] }}
Контакт-центр: {{ $attributes['queue_display'] }}
Причина: {{ $attributes['departure_reason'] }}
@if (!empty($attributes['wait_duration']))
Время ожидания: {{ $attributes['wait_duration'] }}
@endif
ID вызова: {{ $attributes['call_uuid'] }}

Спасибо,
Команда {{ config('app.name', 'FS PBX') }}
