{{-- email-template
format: text
layout: none
--}}
Эскалация голосовой почты {{ $notification->status === 'accepted' ? 'УСПЕШНО' : 'ОШИБКА' }}

Оповещение о голосовой почте для ящика {{ $notification->mailbox ?? 'Неизвестно' }} завершено со статусом {{ $notification->status === 'accepted' ? 'УСПЕШНО' : 'ОШИБКА' }}.

Почтовый ящик: {{ $notification->mailbox ?? '—' }}
Статус: {{ $notification->status === 'accepted' ? 'УСПЕШНО' : 'ОШИБКА' }}
Имя АОН: {{ $notification->caller_id_name ?? '—' }}
Номер АОН: {{ $notification->caller_id_number ?? '—' }}
Длительность сообщения: {{ $notification->message_length_seconds ?? '—' }} секунд
Оставлено: {{ optional($notification->message_left_at)?->copy()->timezone($tenantTimeZone)->format('Y-m-d g:i:s A T') ?? '—' }}
Принято номером: {{ $notification->accepted_by_number ?? '—' }}
Номер повторной попытки: {{ $notification->current_retry ?? 0 }}
Последний приоритет: {{ $notification->current_priority ?? '—' }}
Идентификатор уведомления: {{ $notification->vm_notify_notification_uuid }}

@if($notification->attempts->count())
Попытки:
@foreach($notification->attempts as $attempt)
{{ $attempt->destination ?? '—' }} | {{ $attempt->status ?? '—' }} | Повторная попытка {{ $attempt->retry_number ?? '—' }} | Приоритет {{ $attempt->priority ?? '—' }} | {{ $attempt->claim_result ?? '—' }}
@endforeach
@endif

@if($template_logs->count())
Журнал уведомлений:
@foreach($template_logs as $log)
{{ $log['time'] }} | {{ $log['level'] }} | {{ $log['message'] }} | {{ $log['destination'] }} | Повторная попытка {{ $log['retry_number'] }} | Приоритет {{ $log['priority'] }}
@endforeach
@endif

Это письмо автоматически сформировано системой {{ config('app.name', 'FS PBX') }} Эскалация голосовой почты.
