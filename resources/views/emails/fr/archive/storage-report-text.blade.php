{{-- email-template
format: text
layout: none
--}}
Nouveau rapport de stockage d’archives

Le script de transfert est terminé.

Serveur : {{ $attributes['hostname'] ?? 'inconnu' }}
Réussites : {{ isset($attributes['success']) ? count($attributes['success']) : 0 }}
Échoué : {{ isset($attributes['failed']) ? count($attributes['failed']) : 0 }}

@if(isset($attributes['failed']) && count($attributes['failed']) > 0)
Enregistrements en échec :
@foreach($attributes['failed'] as $record)
- {{ $record['name'] }} — {{ $record['msg'] }}
@endforeach
@endif
