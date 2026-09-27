{{-- email-template
version: 1.0.0
language: es-419
category: voicemail
subcategory: escalation-completion
format: html
layout: none
subject: Escalamiento del mensaje de voz para el buzón {{ $notification->mailbox ?? '—' }}: {{ $notification->status === 'accepted' ? 'CORRECTO' : 'FALLIDO' }}
description: Informe de finalización del escalamiento de un mensaje de voz
--}}
<!DOCTYPE html>
<html lang="es-419">
<head>
    <meta charset="utf-8">
    <title>Escalamiento del mensaje de voz para el buzón {{ $notification->mailbox ?? '—' }}: {{ $notification->status === 'accepted' ? 'CORRECTO' : 'FALLIDO' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h2 style="margin-bottom: 16px;">Escalado de buzón de voz {{ $notification->status === 'accepted' ? 'CORRECTO' : 'FALLIDO' }}</h2>

    <p>
        La escalación del correo de voz para el buzón <strong>{{ $notification->mailbox ?? 'Desconocido' }}</strong>
        finalizó con el estado <strong>{{ $notification->status === 'accepted' ? 'CORRECTO' : 'FALLIDO' }}</strong>.
    </p>

    <h3 style="margin-top: 24px;">Detalles del mensaje</h3>
    <table cellpadding="6" cellspacing="0" border="0">
        <tr>
            <td><strong>Buzón:</strong></td>
            <td>{{ $notification->mailbox ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Estado:</strong></td>
            <td>{{ $notification->status === 'accepted' ? 'CORRECTO' : 'FALLIDO' }}</td>
        </tr>
        <tr>
            <td><strong>Nombre del identificador de llamadas:</strong></td>
            <td>{{ $notification->caller_id_name ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Número del identificador de llamadas:</strong></td>
            <td>{{ $notification->caller_id_number ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Duración del mensaje:</strong></td>
            <td>{{ $notification->message_length_seconds ?? '—' }} segundos</td>
        </tr>
        <tr>
            <td><strong>Dejado el:</strong></td>
            <td>{{ optional($notification->message_left_at)?->copy()->timezone($tenantTimeZone)->format('Y-m-d g:i:s A T') ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Aceptado por:</strong></td>
            <td>{{ $notification->accepted_by_number ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Número de reintento:</strong></td>
            <td>{{ $notification->current_retry ?? 0 }}</td>
        </tr>
        <tr>
            <td><strong>Prioridad final:</strong></td>
            <td>{{ $notification->current_priority ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>ID de notificación:</strong></td>
            <td>{{ $notification->vm_notify_notification_uuid }}</td>
        </tr>
    </table>

    @if($notification->attempts->count())
        <h3 style="margin-top: 24px;">Intentos</h3>
        <table cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th align="left">Destino</th>
                    <th align="left">Estado</th>
                    <th align="left">Reintento</th>
                    <th align="left">Prioridad</th>
                    <th align="left">Resultado de la aceptación</th>
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
        <h3 style="margin-top: 24px;">Registro de notificaciones</h3>
        <table cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th align="left">Hora</th>
                    <th align="left">Nivel</th>
                    <th align="left">Mensaje</th>
                    <th align="left">Destino</th>
                    <th align="left">Reintento</th>
                    <th align="left">Prioridad</th>
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
           Este correo fue generado automáticamente por {{ config('app.name', 'FS PBX') }} Escalado de buzón de voz.
    </p>
</body>
</html>
