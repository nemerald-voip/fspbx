{{-- email-template
format: text
layout: none
--}}
Bienvenue, {{ $attributes['recipient_name'] }} !

Nous avons configuré le poste {{ $attributes['extension'] }} pour vous. Conservez cet e-mail pour retrouver les informations de votre téléphone et de votre messagerie vocale.

Poste : {{ $attributes['extension'] }}
@if (!empty($attributes['direct_numbers']))
Numéros directs : {{ implode(', ', $attributes['direct_numbers']) }}
@endif
Boîte vocale : {{ $attributes['voicemail_id'] }}
Code PIN de messagerie vocale : {{ $attributes['voicemail_pin'] }}

CONFIGURER VOTRE MESSAGE D’ACCUEIL VOCAL

1. Composez le *97 sur votre téléphone.
2. Saisissez le code PIN de votre messagerie vocale, puis appuyez sur #.
3. Appuyez sur 5 pour accéder aux options de la messagerie.
4. Appuyez sur 1 pour enregistrer votre message d’indisponibilité.

@if (!empty($attributes['help_url']))
Aide : {{ $attributes['help_url'] }}
@endif
@if (!empty($attributes['support_email']))
Des questions ? Écrivez à {{ $attributes['support_email'] }}.
@endif

Bienvenue,
{{ $attributes['app_name'] }}
