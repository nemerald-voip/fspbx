{{-- email-template
format: text
layout: none
--}}
Tus archivos se están enviando por fax a {{ $attributes['fax_destination'] }}.

Recibirás más notificaciones sobre el resultado de la transmisión.

Si tienes alguna pregunta, escribe a nuestro equipo de atención al cliente a {{ $attributes['support_email'] ?? '' }}.

Gracias,
El equipo de {{ config('app.name', 'Laravel') }}

P. D. ¿Necesitas ayuda inmediata? Visita {{ $attributes['help_url'] ?? '' }} o responde a este correo.
