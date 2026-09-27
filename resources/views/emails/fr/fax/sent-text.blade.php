{{-- email-template
format: text
layout: none
--}}
Votre fax au {{ $attributes['fax_destination'] }} a été livré.

Le fax a été transmis avec succès le {{ $attributes['fax_date'] ?? now()->format('Y-m-d H:i') }}.

@if (!empty($attributes['fax_pages']))
Pages envoyées : {{ $attributes['fax_pages'] }}@if (isset($attributes['fax_total_pages']) && $attributes['fax_total_pages'] !== $attributes['fax_pages']) sur {{ $attributes['fax_total_pages'] }}@endif.
@endif
@if (!empty($attributes['fax_duration_formatted']))
Durée : {{ $attributes['fax_duration_formatted'] }}.
@endif

Le fax transmis est joint à cet e-mail pour vos archives.

Merci,
L’équipe {{ config('app.name', 'Laravel') }}
