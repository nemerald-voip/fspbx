{{-- email-template
format: text
layout: none
--}}
Новый отчёт об архивном хранилище

Скрипт переноса завершён.

Сервер: {{ $attributes['hostname'] ?? 'неизвестно' }}
Успешно: {{ isset($attributes['success']) ? count($attributes['success']) : 0 }}
Ошибка: {{ isset($attributes['failed']) ? count($attributes['failed']) : 0 }}

@if(isset($attributes['failed']) && count($attributes['failed']) > 0)
Записи с ошибками:
@foreach($attributes['failed'] as $record)
- {{ $record['name'] }} — {{ $record['msg'] }}
@endforeach
@endif
