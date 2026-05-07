@php
    $senderName = $attributes['caller_id_name'] ?? '';
    $senderNumber = $attributes['caller_id_number'] ?? '';
    $sender = trim($senderName . ($senderNumber ? ' <' . $senderNumber . '>' : ''));
    $destination = $attributes['fax_destination'] ?? ($attributes['fax_extension'] ?? 'your fax line');
@endphp

Fax received{{ $attributes['caller_id_number'] ? ' from ' . $attributes['caller_id_number'] : '' }}.

A new fax was received for {{ $destination }} and is attached to this email.

{{-- Domain: {{ $attributes['domain_name'] ?? '' }} --}}
From: {{ $sender }}
To: {{ $destination }}
Pages: {{ $attributes['fax_pages'] ?? '' }}
{{-- Status: {{ $attributes['fax_result_text'] ?? '' }} --}}

The fax is attached as a {{ ($attributes['attachment_mime'] ?? '') === 'application/pdf' ? 'PDF' : 'TIFF' }} file.

Questions? Email our customer success team:
{{ $attributes['support_email'] ?? '' }}

Thanks,
{{ config('app.name', 'Laravel') }} Team
