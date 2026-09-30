{{-- email-template
format: text
layout: none
--}}
Te damos la bienvenida a tu aplicación {{ config('app.name', 'Laravel') }}. Guarda una copia de este correo para consultarlo después. Sigue estos sencillos pasos para comenzar a usar la aplicación:

Descarga la aplicación para tus dispositivos:

Google Play: {{ $attributes['google_play_link'] ?? '' }}
Apple Store: {{ $attributes['apple_store_link'] ?? '' }}
Descargar para Windows ({{ $attributes['windows_link'] ?? '' }})
Descargar para Mac ({{ $attributes['mac_link'] ?? '' }})

Nombre para mostrar: {{ $attributes['name'] ?? ''}}
Extensión de PBX: {{ $attributes['extension'] ?? ''}}

Usa estas credenciales para iniciar sesión:

Dominio: {{ $attributes['domain'] ?? ''}}
Usuario: {{ $attributes['username'] ?? ''}}
@if(!empty($attributes['password_url']))
Contraseña: {{ $attributes['password_url'] }}
@elseif(!empty($attributes['password']))
Contraseña: {{ $attributes['password'] }}
@endif

Después de iniciar sesión, puedes comunicarte con los usuarios de tu organización: hacer y recibir llamadas desde tu extensión, ponerlas en espera, transferirlas, estacionarlas y mucho más.

Si tienes alguna pregunta, escribe a nuestro equipo de atención al cliente a {{ $attributes['support_email'] ?? '' }}. (Respondemos muy rápido.)

Gracias,
El equipo de {{ config('app.name', 'Laravel') }}

P. D. ¿Necesitas ayuda para comenzar? El equipo de soporte de {{ config('app.name', 'Laravel') }} siempre está listo para ayudarte. Solo responde a este correo.
