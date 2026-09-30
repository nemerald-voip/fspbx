{{-- email-template
format: text
layout: none
--}}
Hola,

Este es un correo de prueba de {{ config('app.name', 'FS PBX') }}.

Si recibiste este mensaje, el servicio de correo configurado puede enviar correos electrónicos.

Enviado el {{ $attributes['sent_at'] ?? now()->toDateTimeString() }}.
