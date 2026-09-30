{{-- email-template
format: text
layout: none
--}}
Appel manqué{{ $attributes['caller_id_number'] ? ' de ' . $attributes['caller_id_number'] : '' }}.

Un appel vers {{ $attributes['ring_group_display'] ?: 'votre groupe de sonnerie' }} est resté sans réponse.

De : {{ $attributes['caller_display'] ?: 'Appelant inconnu' }}
À : {{ $attributes['ring_group_display'] ?: 'Groupe de sonnerie' }}
@if (!empty($attributes['destination_number']))
Numéro composé : {{ $attributes['destination_number'] }}
@endif

Merci,
L’équipe {{ config('app.name', 'FS PBX') }}
