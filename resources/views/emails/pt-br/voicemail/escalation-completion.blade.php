{{-- email-template
version: 1.0.0
language: pt-br
category: voicemail
subcategory: escalation-completion
format: html
layout: none
subject: Escalonamento da mensagem de voz para a caixa postal {{ $notification->mailbox ?? '—' }}: {{ $notification->status === 'accepted' ? 'CONCLUÍDO' : 'FALHOU' }}
description: Relatório de conclusão do escalonamento de mensagem de voz
--}}
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Escalonamento da mensagem de voz para a caixa postal {{ $notification->mailbox ?? '—' }}: {{ $notification->status === 'accepted' ? 'CONCLUÍDO' : 'FALHOU' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h2 style="margin-bottom: 16px;">Escalonamento de Correio de Voz {{ $notification->status === 'accepted' ? 'CONCLUÍDO' : 'FALHOU' }}</h2>

    <p>
        O escalonamento do correio de voz para a caixa postal <strong>{{ $notification->mailbox ?? 'Desconhecido' }}</strong>
        foi concluído com o status <strong>{{ $notification->status === 'accepted' ? 'CONCLUÍDO' : 'FALHOU' }}</strong>.
    </p>

    <h3 style="margin-top: 24px;">Detalhes da mensagem</h3>
    <table cellpadding="6" cellspacing="0" border="0">
        <tr>
            <td><strong>Caixa Postal:</strong></td>
            <td>{{ $notification->mailbox ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Status:</strong></td>
            <td>{{ $notification->status === 'accepted' ? 'CONCLUÍDO' : 'FALHOU' }}</td>
        </tr>
        <tr>
            <td><strong>Nome do Identificador de Chamadas:</strong></td>
            <td>{{ $notification->caller_id_name ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Número do Identificador de Chamadas:</strong></td>
            <td>{{ $notification->caller_id_number ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Duração da mensagem:</strong></td>
            <td>{{ $notification->message_length_seconds ?? '—' }} segundos</td>
        </tr>
        <tr>
            <td><strong>Deixada em:</strong></td>
            <td>{{ optional($notification->message_left_at)?->copy()->timezone($tenantTimeZone)->format('Y-m-d g:i:s A T') ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Aceito por:</strong></td>
            <td>{{ $notification->accepted_by_number ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Número da nova tentativa:</strong></td>
            <td>{{ $notification->current_retry ?? 0 }}</td>
        </tr>
        <tr>
            <td><strong>Prioridade final:</strong></td>
            <td>{{ $notification->current_priority ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>ID da notificação:</strong></td>
            <td>{{ $notification->vm_notify_notification_uuid }}</td>
        </tr>
    </table>

    @if($notification->attempts->count())
        <h3 style="margin-top: 24px;">Tentativas</h3>
        <table cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th align="left">Destino</th>
                    <th align="left">Status</th>
                    <th align="left">Tentar Novamente</th>
                    <th align="left">Prioridade</th>
                    <th align="left">Resultado da aceitação</th>
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
        <h3 style="margin-top: 24px;">Registro de notificações</h3>
        <table cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th align="left">Horário</th>
                    <th align="left">Nível</th>
                    <th align="left">Mensagem</th>
                    <th align="left">Destino</th>
                    <th align="left">Tentar Novamente</th>
                    <th align="left">Prioridade</th>
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
           Este e-mail foi gerado automaticamente pelo {{ config('app.name', 'FS PBX') }} Escalonamento de Correio de Voz.
    </p>
</body>
</html>
