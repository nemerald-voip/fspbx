{{-- email-template
format: text
layout: none
--}}
Ваш факс на номер {{ $attributes['fax_destination'] }} доставлен.

Факс успешно передан {{ $attributes['fax_date'] ?? now()->format('Y-m-d H:i') }}.

@if (!empty($attributes['fax_pages']))
Отправлено страниц: {{ $attributes['fax_pages'] }}@if (isset($attributes['fax_total_pages']) && $attributes['fax_total_pages'] !== $attributes['fax_pages']) из {{ $attributes['fax_total_pages'] }}@endif.
@endif
@if (!empty($attributes['fax_duration_formatted']))
Длительность: {{ $attributes['fax_duration_formatted'] }}.
@endif

Переданный факс приложен к письму для ваших записей.

Спасибо,
Команда {{ config('app.name', 'Laravel') }}
