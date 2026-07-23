{{-- email-template
format: text
layout: none
--}}
Your file(s) are now being faxed to {{ $attributes['fax_destination'] }}.

Additional notifications will follow regarding the outcome of the transmission.

If you have any questions, email our customer success team at {{ $attributes['support_email'] ?? '' }}.

Thanks,
{{ config('app.name', 'Laravel') }} Team

P.S. Need immediate help? Visit {{ $attributes['help_url'] ?? '' }} or reply to this email.
