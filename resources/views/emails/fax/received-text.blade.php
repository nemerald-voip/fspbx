{{-- email-template
format: text
layout: none
--}}
Fax received{{ $attributes['caller_id_number'] ? ' from ' . $attributes['caller_id_number'] : '' }}.

A new fax was received for {{ $attributes['fax_destination'] }} and is attached to this email.

{{-- Domain: {{ $attributes['domain_name'] ?? '' }} --}}
From: {{ $attributes['caller_display'] }}
To: {{ $attributes['fax_destination'] }}
Pages: {{ $attributes['fax_pages'] ?? '' }}
@if (!empty($attributes['fax_date']))
Received: {{ $attributes['fax_date'] }}
@endif
{{-- Status: {{ $attributes['fax_result_text'] ?? '' }} --}}

The fax is attached as a {{ ($attributes['attachment_mime'] ?? '') === 'application/pdf' ? 'PDF' : 'TIFF' }} file.

Questions? Email our customer success team:
{{ $attributes['support_email'] ?? '' }}

Thanks,
{{ config('app.name', 'Laravel') }} Team
