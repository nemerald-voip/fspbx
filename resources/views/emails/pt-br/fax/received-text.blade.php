{{-- email-template
format: text
layout: none
--}}
Fax recebido{{ $attributes['caller_id_number'] ? ' de ' . $attributes['caller_id_number'] : '' }}.

Foi recebido um novo fax para {{ $attributes['fax_destination'] }} e está anexado a este e-mail.

{{-- Domain: {{ $attributes['domain_name'] ?? '' }} --}}
De: {{ $attributes['caller_display'] }}
Para: {{ $attributes['fax_destination'] }}
Páginas: {{ $attributes['fax_pages'] ?? '' }}
@if (!empty($attributes['fax_date']))
Recebido: {{ $attributes['fax_date'] }}
@endif
{{-- Status: {{ $attributes['fax_result_text'] ?? '' }} --}}

O fax está anexado como arquivo {{ ($attributes['attachment_mime'] ?? '') === 'application/pdf' ? 'PDF' : 'TIFF' }}.

Dúvidas? Escreva para nossa equipe de atendimento ao cliente:
{{ $attributes['support_email'] ?? '' }}

Obrigado,
A equipe de {{ config('app.name', 'Laravel') }}
