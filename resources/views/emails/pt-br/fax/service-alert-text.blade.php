{{-- email-template
format: text
layout: none
--}}
Alerta do serviço de fax

@if(isset($attributes["pendingFaxes"]))
{{ $attributes["pendingFaxes"] }} faxes de saída estão pendentes há mais de {{ $attributes["waitTimeThreshold"] }} minutos. Verifique o status do serviço de fax.
@endif

@if(isset($attributes["failedFaxes"]))
{{ $attributes["failedFaxes"] }} de {{ $attributes["totalChecked"] }} faxes processados recentemente falharam ({{ $attributes["failureRate"] }}% de falhas).
Isso pode indicar um problema com o serviço de fax.
@endif

Obrigado,

A equipe de {{ config('app.name', 'Laravel') }}
