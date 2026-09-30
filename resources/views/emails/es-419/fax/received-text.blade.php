{{-- email-template
format: text
layout: none
--}}
Fax recibido{{ $attributes['caller_id_number'] ? ' de ' . $attributes['caller_id_number'] : '' }}.

Se recibió un nuevo fax para {{ $attributes['fax_destination'] }} y está adjunto a este correo.

{{-- Domain: {{ $attributes['domain_name'] ?? '' }} --}}
De: {{ $attributes['caller_display'] }}
Para: {{ $attributes['fax_destination'] }}
Páginas: {{ $attributes['fax_pages'] ?? '' }}
@if (!empty($attributes['fax_date']))
Recibido: {{ $attributes['fax_date'] }}
@endif
{{-- Status: {{ $attributes['fax_result_text'] ?? '' }} --}}

El fax está adjunto como archivo {{ ($attributes['attachment_mime'] ?? '') === 'application/pdf' ? 'PDF' : 'TIFF' }}.

¿Tienes preguntas? Escribe a nuestro equipo de atención al cliente:
{{ $attributes['support_email'] ?? '' }}

Gracias,
El equipo de {{ config('app.name', 'Laravel') }}
