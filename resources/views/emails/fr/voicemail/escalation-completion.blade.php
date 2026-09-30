{{-- email-template
version: 1.0.0
language: fr
category: voicemail
subcategory: escalation-completion
format: html
layout: none
subject: Escalade du message vocal pour la boîte {{ $notification->mailbox ?? '—' }}: {{ $notification->status === 'accepted' ? 'RÉUSSIE' : 'ÉCHEC' }}
description: Rapport de fin d’escalade de messagerie vocale
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Escalade du message vocal pour la boîte {{ $notification->mailbox ?? '—' }}: {{ $notification->status === 'accepted' ? 'RÉUSSIE' : 'ÉCHEC' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h2 style="margin-bottom: 16px;">Escalade de la messagerie vocale {{ $notification->status === 'accepted' ? 'RÉUSSIE' : 'ÉCHEC' }}</h2>

    <p>
        L’escalade de messagerie vocale pour la boîte <strong>{{ $notification->mailbox ?? 'Inconnu' }}</strong>
        s’est terminée avec le statut <strong>{{ $notification->status === 'accepted' ? 'RÉUSSIE' : 'ÉCHEC' }}</strong>.
    </p>

    <h3 style="margin-top: 24px;">Détails du message</h3>
    <table cellpadding="6" cellspacing="0" border="0">
        <tr>
            <td><strong>Boîte aux lettres :</strong></td>
            <td>{{ $notification->mailbox ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Statut :</strong></td>
            <td>{{ $notification->status === 'accepted' ? 'RÉUSSIE' : 'ÉCHEC' }}</td>
        </tr>
        <tr>
            <td><strong>Nom de présentation :</strong></td>
            <td>{{ $notification->caller_id_name ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Numéro de présentation :</strong></td>
            <td>{{ $notification->caller_id_number ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Durée du message :</strong></td>
            <td>{{ $notification->message_length_seconds ?? '—' }} secondes</td>
        </tr>
        <tr>
            <td><strong>Déposé le :</strong></td>
            <td>{{ optional($notification->message_left_at)?->copy()->timezone($tenantTimeZone)->format('Y-m-d g:i:s A T') ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Accepté par :</strong></td>
            <td>{{ $notification->accepted_by_number ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Numéro de nouvelle tentative :</strong></td>
            <td>{{ $notification->current_retry ?? 0 }}</td>
        </tr>
        <tr>
            <td><strong>Priorité finale :</strong></td>
            <td>{{ $notification->current_priority ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Identifiant de notification :</strong></td>
            <td>{{ $notification->vm_notify_notification_uuid }}</td>
        </tr>
    </table>

    @if($notification->attempts->count())
        <h3 style="margin-top: 24px;">Tentatives</h3>
        <table cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th align="left">Destination</th>
                    <th align="left">Statut</th>
                    <th align="left">Nouvelle tentative</th>
                    <th align="left">Priorité</th>
                    <th align="left">Résultat de la prise en charge</th>
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
        <h3 style="margin-top: 24px;">Journal des notifications</h3>
        <table cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th align="left">Heure</th>
                    <th align="left">Niveau</th>
                    <th align="left">Message</th>
                    <th align="left">Destination</th>
                    <th align="left">Nouvelle tentative</th>
                    <th align="left">Priorité</th>
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
           Cet e-mail a été généré automatiquement par {{ config('app.name', 'FS PBX') }} Escalade de la messagerie vocale.
    </p>
</body>
</html>
