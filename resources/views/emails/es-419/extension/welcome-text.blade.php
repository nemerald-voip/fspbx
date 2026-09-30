{{-- email-template
format: text
layout: none
--}}
¡Bienvenido, {{ $attributes['recipient_name'] }}!

Configuramos la extensión {{ $attributes['extension'] }} para ti. Guarda este correo para consultar los datos de tu teléfono y correo de voz.

Extensión: {{ $attributes['extension'] }}
@if (!empty($attributes['direct_numbers']))
Números directos: {{ implode(', ', $attributes['direct_numbers']) }}
@endif
Buzón de voz: {{ $attributes['voicemail_id'] }}
PIN de buzón de voz: {{ $attributes['voicemail_pin'] }}

CONFIGURA TU SALUDO DEL CORREO DE VOZ

1. Marca *97 desde tu teléfono.
2. Ingresa el PIN de tu correo de voz y presiona #.
3. Presiona 5 para acceder a las opciones del buzón.
4. Presiona 1 para grabar tu saludo de no disponible.

@if (!empty($attributes['help_url']))
Ayuda: {{ $attributes['help_url'] }}
@endif
@if (!empty($attributes['support_email']))
¿Tienes preguntas? Escribe a {{ $attributes['support_email'] }}.
@endif

Te damos la bienvenida,
{{ $attributes['app_name'] }}
