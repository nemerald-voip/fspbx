Fax service alert

{{ $attributes["pendingFaxes"] ?? ''}} faxes have been pending for longer than {{ $attributes["waitTimeThreshold"] ?? ''}} minutes. Check fax queue service status.

Thanks,

{{ config('app.name', 'Laravel') }} Team