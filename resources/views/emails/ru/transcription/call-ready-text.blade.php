{{-- email-template
format: text
layout: none
--}}
ОТЧЁТ О РАСШИФРОВКЕ ЗВОНКА
=========================
Дата:      {{ $data['date'] }}
Длительность:  {{ $data['duration'] }}
Тональность: {{ ['positive' => 'Положительная', 'negative' => 'Отрицательная', 'neutral' => 'Нейтральная'][strtolower($data['sentiment'])] ?? $data['sentiment'] }}

КРАТКОЕ СОДЕРЖАНИЕ
-----------------
"{{ $data['summary'] }}"

@if(!empty($data['action_items']))
ЗАДАЧИ И СЛЕДУЮЩИЕ ШАГИ
-------------------------
@foreach($data['action_items'] as $item)
[ ] @if($item['owner'])({{ $item['owner'] }}) @endif{{ $item['description'] }}
@endforeach
@endif
