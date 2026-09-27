{{-- email-template
format: text
layout: none
--}}
Vos fichiers sont en cours d’envoi par fax au {{ $attributes['fax_destination'] }}.

D’autres notifications vous informeront du résultat de la transmission.

Si vous avez des questions, contactez notre équipe d’assistance à l’adresse {{ $attributes['support_email'] ?? '' }}.

Merci,
L’équipe {{ config('app.name', 'Laravel') }}

P.-S. Besoin d’aide rapidement ? Consultez {{ $attributes['help_url'] ?? '' }} ou répondez à cet e-mail.
