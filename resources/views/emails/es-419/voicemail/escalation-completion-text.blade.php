{{-- email-template
format: text
layout: none
--}}
Escalado de buzón de voz {{ $notification->status === 'accepted' ? 'CORRECTO' : 'FALLIDO' }}

La escalación del correo de voz para el buzón {{ $notification->mailbox ?? 'Desconocido' }} finalizó con el estado {{ $notification->status === 'accepted' ? 'CORRECTO' : 'FALLIDO' }}.

Buzón: {{ $notification->mailbox ?? '—' }}
Estado: {{ $notification->status === 'accepted' ? 'CORRECTO' : 'FALLIDO' }}
Nombre del identificador de llamadas: {{ $notification->caller_id_name ?? '—' }}
Número del identificador de llamadas: {{ $notification->caller_id_number ?? '—' }}
Duración del mensaje: {{ $notification->message_length_seconds ?? '—' }} segundos
Dejado el: {{ optional($notification->message_left_at)?->copy()->timezone($tenantTimeZone)->format('Y-m-d g:i:s A T') ?? '—' }}
Aceptado por: {{ $notification->accepted_by_number ?? '—' }}
Número de reintento: {{ $notification->current_retry ?? 0 }}
Prioridad final: {{ $notification->current_priority ?? '—' }}
ID de notificación: {{ $notification->vm_notify_notification_uuid }}

@if($notification->attempts->count())
Intentos:
@foreach($notification->attempts as $attempt)
{{ $attempt->destination ?? '—' }} | {{ $attempt->status ?? '—' }} | Reintento {{ $attempt->retry_number ?? '—' }} | Prioridad {{ $attempt->priority ?? '—' }} | {{ $attempt->claim_result ?? '—' }}
@endforeach
@endif

@if($template_logs->count())
Registro de notificaciones:
@foreach($template_logs as $log)
{{ $log['time'] }} | {{ $log['level'] }} | {{ $log['message'] }} | {{ $log['destination'] }} | Reintento {{ $log['retry_number'] }} | Prioridad {{ $log['priority'] }}
@endforeach
@endif

Este correo fue generado automáticamente por {{ config('app.name', 'FS PBX') }} Escalado de buzón de voz.
