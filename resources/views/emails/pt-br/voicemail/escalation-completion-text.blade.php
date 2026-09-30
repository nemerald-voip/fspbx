{{-- email-template
format: text
layout: none
--}}
Escalonamento de Correio de Voz {{ $notification->status === 'accepted' ? 'CONCLUÍDO' : 'FALHOU' }}

O escalonamento do correio de voz para a caixa postal {{ $notification->mailbox ?? 'Desconhecido' }} foi concluído com o status {{ $notification->status === 'accepted' ? 'CONCLUÍDO' : 'FALHOU' }}.

Caixa Postal: {{ $notification->mailbox ?? '—' }}
Status: {{ $notification->status === 'accepted' ? 'CONCLUÍDO' : 'FALHOU' }}
Nome do Identificador de Chamadas: {{ $notification->caller_id_name ?? '—' }}
Número do Identificador de Chamadas: {{ $notification->caller_id_number ?? '—' }}
Duração da mensagem: {{ $notification->message_length_seconds ?? '—' }} segundos
Deixada em: {{ optional($notification->message_left_at)?->copy()->timezone($tenantTimeZone)->format('Y-m-d g:i:s A T') ?? '—' }}
Aceito por: {{ $notification->accepted_by_number ?? '—' }}
Número da nova tentativa: {{ $notification->current_retry ?? 0 }}
Prioridade final: {{ $notification->current_priority ?? '—' }}
ID da notificação: {{ $notification->vm_notify_notification_uuid }}

@if($notification->attempts->count())
Tentativas:
@foreach($notification->attempts as $attempt)
{{ $attempt->destination ?? '—' }} | {{ $attempt->status ?? '—' }} | Tentar Novamente {{ $attempt->retry_number ?? '—' }} | Prioridade {{ $attempt->priority ?? '—' }} | {{ $attempt->claim_result ?? '—' }}
@endforeach
@endif

@if($template_logs->count())
Registro de notificações:
@foreach($template_logs as $log)
{{ $log['time'] }} | {{ $log['level'] }} | {{ $log['message'] }} | {{ $log['destination'] }} | Tentar Novamente {{ $log['retry_number'] }} | Prioridade {{ $log['priority'] }}
@endforeach
@endif

Este e-mail foi gerado automaticamente pelo {{ config('app.name', 'FS PBX') }} Escalonamento de Correio de Voz.
