{{-- email-template
format: text
layout: none
--}}
Пропущенный вызов{{ $attributes['caller_id_number'] ? ' от ' . $attributes['caller_id_number'] : '' }}.

Вызов на {{ $attributes['ring_group_display'] ?: 'вашу группу вызова' }} остался без ответа.

От: {{ $attributes['caller_display'] ?: 'Неизвестный абонент' }}
Кому: {{ $attributes['ring_group_display'] ?: 'Группа вызова' }}
@if (!empty($attributes['destination_number']))
Набранный номер: {{ $attributes['destination_number'] }}
@endif

Спасибо,
Команда {{ config('app.name', 'FS PBX') }}
