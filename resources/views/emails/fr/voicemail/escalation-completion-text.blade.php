{{-- email-template
format: text
layout: none
--}}
Escalade de la messagerie vocale {{ $notification->status === 'accepted' ? 'RÉUSSIE' : 'ÉCHEC' }}

L’escalade de messagerie vocale pour la boîte {{ $notification->mailbox ?? 'Inconnu' }} s’est terminée avec le statut {{ $notification->status === 'accepted' ? 'RÉUSSIE' : 'ÉCHEC' }}.

Boîte aux lettres : {{ $notification->mailbox ?? '—' }}
Statut : {{ $notification->status === 'accepted' ? 'RÉUSSIE' : 'ÉCHEC' }}
Nom de présentation : {{ $notification->caller_id_name ?? '—' }}
Numéro de présentation : {{ $notification->caller_id_number ?? '—' }}
Durée du message : {{ $notification->message_length_seconds ?? '—' }} secondes
Déposé le : {{ optional($notification->message_left_at)?->copy()->timezone($tenantTimeZone)->format('Y-m-d g:i:s A T') ?? '—' }}
Accepté par : {{ $notification->accepted_by_number ?? '—' }}
Numéro de nouvelle tentative : {{ $notification->current_retry ?? 0 }}
Priorité finale : {{ $notification->current_priority ?? '—' }}
Identifiant de notification : {{ $notification->vm_notify_notification_uuid }}

@if($notification->attempts->count())
Tentatives :
@foreach($notification->attempts as $attempt)
{{ $attempt->destination ?? '—' }} | {{ $attempt->status ?? '—' }} | Nouvelle tentative {{ $attempt->retry_number ?? '—' }} | Priorité {{ $attempt->priority ?? '—' }} | {{ $attempt->claim_result ?? '—' }}
@endforeach
@endif

@if($template_logs->count())
Journal des notifications :
@foreach($template_logs as $log)
{{ $log['time'] }} | {{ $log['level'] }} | {{ $log['message'] }} | {{ $log['destination'] }} | Nouvelle tentative {{ $log['retry_number'] }} | Priorité {{ $log['priority'] }}
@endforeach
@endif

Cet e-mail a été généré automatiquement par {{ config('app.name', 'FS PBX') }} Escalade de la messagerie vocale.
