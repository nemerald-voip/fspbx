{{-- email-template
format: text
layout: none
--}}
Hola{{ isset($attributes['name']) ? ' ' . $attributes['name'] : '' }},

Recibes este correo porque recibimos una solicitud para restablecer la contraseña de tu cuenta de {{ config('app.name', 'Laravel') }}.

Restablece tu contraseña: {{ $attributes['url'] ?? '' }}

Este enlace para restablecer la contraseña vencerá en {{ $attributes['expire_minutes'] ?? '' }} minutos.

Si no solicitaste restablecer tu contraseña, no necesitas hacer nada.

Si tienes alguna pregunta, escribe a nuestro equipo de atención al cliente a {{ $attributes['support_email'] ?? '' }}.

Cuida tu seguridad,
El equipo de {{ config('app.name', 'Laravel') }}

No respondas a este correo: es un mensaje automático y las respuestas no se revisan.
