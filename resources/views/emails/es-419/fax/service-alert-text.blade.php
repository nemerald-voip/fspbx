{{-- email-template
format: text
layout: none
--}}
Alerta del servicio de fax

@if(isset($attributes["pendingFaxes"]))
{{ $attributes["pendingFaxes"] }} faxes salientes llevan pendientes más de {{ $attributes["waitTimeThreshold"] }} minutos. Revisa el estado del servicio de fax.
@endif

@if(isset($attributes["failedFaxes"]))
{{ $attributes["failedFaxes"] }} de {{ $attributes["totalChecked"] }} faxes procesados recientemente fallaron ({{ $attributes["failureRate"] }}% de fallas).
Esto puede indicar un problema con el servicio de fax.
@endif

Gracias,

El equipo de {{ config('app.name', 'Laravel') }}
