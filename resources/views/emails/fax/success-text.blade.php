Your fax to {{ $attributes['fax_destination'] }} was delivered.

The fax was successfully transmitted on {{ $attributes['fax_date'] ?? now()->format('Y-m-d H:i') }}.

@if (!empty($attributes['fax_pages']))
Pages sent: {{ $attributes['fax_pages'] }}@if (isset($attributes['fax_total_pages']) && $attributes['fax_total_pages'] !== $attributes['fax_pages']) of {{ $attributes['fax_total_pages'] }}@endif.
@endif
@if (!empty($attributes['fax_duration_formatted']))
Duration: {{ $attributes['fax_duration_formatted'] }}.
@endif

The transmitted fax is attached to this email for your records.

Thanks,
{{ config('app.name', 'Laravel') }} Team
