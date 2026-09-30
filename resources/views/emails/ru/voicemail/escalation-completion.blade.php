{{-- email-template
version: 1.0.0
language: ru
category: voicemail
subcategory: escalation-completion
format: html
layout: none
subject: Эскалация голосового сообщения для ящика {{ $notification->mailbox ?? '—' }}: {{ $notification->status === 'accepted' ? 'УСПЕШНО' : 'ОШИБКА' }}
description: Отчёт о завершении эскалации голосового сообщения
--}}
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Эскалация голосового сообщения для ящика {{ $notification->mailbox ?? '—' }}: {{ $notification->status === 'accepted' ? 'УСПЕШНО' : 'ОШИБКА' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h2 style="margin-bottom: 16px;">Эскалация голосовой почты {{ $notification->status === 'accepted' ? 'УСПЕШНО' : 'ОШИБКА' }}</h2>

    <p>
        Оповещение о голосовой почте для ящика <strong>{{ $notification->mailbox ?? 'Неизвестно' }}</strong>
        завершено со статусом <strong>{{ $notification->status === 'accepted' ? 'УСПЕШНО' : 'ОШИБКА' }}</strong>.
    </p>

    <h3 style="margin-top: 24px;">Сведения о сообщении</h3>
    <table cellpadding="6" cellspacing="0" border="0">
        <tr>
            <td><strong>Почтовый ящик:</strong></td>
            <td>{{ $notification->mailbox ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Статус:</strong></td>
            <td>{{ $notification->status === 'accepted' ? 'УСПЕШНО' : 'ОШИБКА' }}</td>
        </tr>
        <tr>
            <td><strong>Имя АОН:</strong></td>
            <td>{{ $notification->caller_id_name ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Номер АОН:</strong></td>
            <td>{{ $notification->caller_id_number ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Длительность сообщения:</strong></td>
            <td>{{ $notification->message_length_seconds ?? '—' }} секунд</td>
        </tr>
        <tr>
            <td><strong>Оставлено:</strong></td>
            <td>{{ optional($notification->message_left_at)?->copy()->timezone($tenantTimeZone)->format('Y-m-d g:i:s A T') ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Принято номером:</strong></td>
            <td>{{ $notification->accepted_by_number ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Номер повторной попытки:</strong></td>
            <td>{{ $notification->current_retry ?? 0 }}</td>
        </tr>
        <tr>
            <td><strong>Последний приоритет:</strong></td>
            <td>{{ $notification->current_priority ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Идентификатор уведомления:</strong></td>
            <td>{{ $notification->vm_notify_notification_uuid }}</td>
        </tr>
    </table>

    @if($notification->attempts->count())
        <h3 style="margin-top: 24px;">Попытки</h3>
        <table cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th align="left">Назначение</th>
                    <th align="left">Статус</th>
                    <th align="left">Повторная попытка</th>
                    <th align="left">Приоритет</th>
                    <th align="left">Результат подтверждения</th>
                </tr>
            </thead>
            <tbody>
                @foreach($notification->attempts as $attempt)
                    <tr>
                        <td>{{ $attempt->destination ?? '—' }}</td>
                        <td>{{ $attempt->status ?? '—' }}</td>
                        <td>{{ $attempt->retry_number ?? '—' }}</td>
                        <td>{{ $attempt->priority ?? '—' }}</td>
                        <td>{{ $attempt->claim_result ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($template_logs->count())
        <h3 style="margin-top: 24px;">Журнал уведомлений</h3>
        <table cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th align="left">Время</th>
                    <th align="left">Уровень</th>
                    <th align="left">Сообщение</th>
                    <th align="left">Назначение</th>
                    <th align="left">Повторная попытка</th>
                    <th align="left">Приоритет</th>
                </tr>
            </thead>
            <tbody>
                @foreach($template_logs as $log)
                    <tr>
                        <td>{{ $log['time'] }}</td>
                        <td>{{ $log['level'] }}</td>
                        <td>{{ $log['message'] }}</td>
                        <td>{{ $log['destination'] }}</td>
                        <td>{{ $log['retry_number'] }}</td>
                        <td>{{ $log['priority'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p style="margin-top: 24px; color: #6B7280; font-size: 12px;">
           Это письмо автоматически сформировано системой {{ config('app.name', 'FS PBX') }} Эскалация голосовой почты.
    </p>
</body>
</html>
