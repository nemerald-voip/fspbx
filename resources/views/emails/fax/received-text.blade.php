New fax received for {{ $attributes['fax_destination'] ?? ($attributes['fax_extension'] ?? 'your fax line') }}.

A new fax has been received and attached to this email.

{{-- Domain: {{ $attributes['domain_name'] ?? '' }} --}}
Fax destination: {{ $attributes['fax_destination'] ?? '' }}
Fax extension: {{ $attributes['fax_extension'] ?? '' }}
Caller ID name: {{ $attributes['caller_id_name'] ?? '' }}
Caller ID number: {{ $attributes['caller_id_number'] ?? '' }}
Pages: {{ $attributes['fax_pages'] ?? '' }}
{{-- Status: {{ $attributes['fax_result_text'] ?? '' }} --}}

Questions? Email our customer success team:
{{ $attributes['support_email'] ?? '' }}

Help documentation:
{{ $attributes['help_url'] ?? '' }}

Thanks,
{{ config('app.name', 'Laravel') }} Team