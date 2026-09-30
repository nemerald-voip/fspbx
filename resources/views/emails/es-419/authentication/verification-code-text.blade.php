{{-- email-template
format: text
layout: none
--}}
Hola{{ isset($attributes['name']) ? ' ' . $attributes['name'] : '' }},

Usa el siguiente código para completar tu autenticación:

Tu código de autenticación de dos factores: {{ $attributes['code'] ?? '' }}

Usa el código anterior para iniciar sesión en tu cuenta de {{ config('app.name', 'Laravel') }}.
Este paso adicional confirma que eres tú quien intenta acceder a tu cuenta.

¿Por qué recibes este correo?
Esta medida de seguridad se activa cuando se habilita la autenticación de dos factores o se intenta iniciar sesión.
Si iniciaste esta acción, usa el código para continuar. Si no solicitaste este código,
no necesitas hacer nada: sin el código, el acceso a tu cuenta permanece protegido.

¿No solicitaste este código?
Si no solicitaste este código o sospechas de alguna actividad no autorizada, protege
tu cuenta de inmediato cambiando tu contraseña y contactando a nuestro equipo de soporte.

Si tienes alguna pregunta, escribe a nuestro equipo de atención al cliente a {{ $attributes['support_email'] ?? '' }}.

Cuida tu seguridad,
El equipo de {{ config('app.name', 'Laravel') }}

P. D. ¿Necesitas ayuda para comenzar? El equipo de soporte de {{ config('app.name', 'Laravel') }} siempre está listo para ayudarte. Solo responde a este correo.

No respondas a este correo: es un mensaje automático y las respuestas no se revisan.
