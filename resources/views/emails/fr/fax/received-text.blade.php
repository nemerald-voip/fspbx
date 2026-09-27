{{-- email-template
format: text
layout: none
--}}
Fax reçu{{ $attributes['caller_id_number'] ? ' de ' . $attributes['caller_id_number'] : '' }}.

Un nouveau fax a été reçu pour {{ $attributes['fax_destination'] }} et est joint à cet e-mail.

{{-- Domain: {{ $attributes['domain_name'] ?? '' }} --}}
De : {{ $attributes['caller_display'] }}
À : {{ $attributes['fax_destination'] }}
Pages : {{ $attributes['fax_pages'] ?? '' }}
@if (!empty($attributes['fax_date']))
Reçu : {{ $attributes['fax_date'] }}
@endif
{{-- Status: {{ $attributes['fax_result_text'] ?? '' }} --}}

Le fax est joint sous forme de fichier {{ ($attributes['attachment_mime'] ?? '') === 'application/pdf' ? 'PDF' : 'TIFF' }}.

Des questions ? Contactez notre équipe d’assistance :
{{ $attributes['support_email'] ?? '' }}

Merci,
L’équipe {{ config('app.name', 'Laravel') }}
