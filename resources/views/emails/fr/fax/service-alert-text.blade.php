{{-- email-template
format: text
layout: none
--}}
Alerte du service de fax

@if(isset($attributes["pendingFaxes"]))
{{ $attributes["pendingFaxes"] }} fax sortants sont en attente depuis plus de {{ $attributes["waitTimeThreshold"] }} minutes. Vérifiez l’état du service de fax.
@endif

@if(isset($attributes["failedFaxes"]))
{{ $attributes["failedFaxes"] }} sur {{ $attributes["totalChecked"] }} fax récemment traités ont échoué ({{ $attributes["failureRate"] }}% d’échecs).
Cela peut indiquer un problème avec le service de fax.
@endif

Merci,

L’équipe {{ config('app.name', 'Laravel') }}
