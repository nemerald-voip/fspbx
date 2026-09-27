{{-- email-template
format: text
layout: none
--}}
Appel abandonné{{ $attributes['caller_id_number'] ? ' de '.$attributes['caller_id_number'] : '' }}.

Un appelant a quitté {{ $attributes['queue_display'] }} avant qu’un agent ne réponde.

De : {{ $attributes['caller_display'] }}
Centre de contact : {{ $attributes['queue_display'] }}
Motif : {{ $attributes['departure_reason'] }}
@if (!empty($attributes['wait_duration']))
Temps d’attente : {{ $attributes['wait_duration'] }}
@endif
ID d'appel : {{ $attributes['call_uuid'] }}

Merci,
L’équipe {{ config('app.name', 'FS PBX') }}
