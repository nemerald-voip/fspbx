{{-- email-template
format: text
layout: none
--}}
Bonjour{{ isset($attributes['name']) ? ' ' . $attributes['name'] : '' }},

Vous recevez cet e-mail car nous avons reçu une demande de réinitialisation du mot de passe de votre compte {{ config('app.name', 'Laravel') }}.

Réinitialisez votre mot de passe : {{ $attributes['url'] ?? '' }}

Ce lien de réinitialisation expirera dans {{ $attributes['expire_minutes'] ?? '' }} minutes.

Si vous n’avez pas demandé de réinitialisation, aucune action n’est nécessaire.

Si vous avez des questions, contactez notre équipe d’assistance à l’adresse {{ $attributes['support_email'] ?? '' }}.

Prenez soin de votre sécurité,
L’équipe {{ config('app.name', 'Laravel') }}

Ne répondez pas à cet e-mail : il s’agit d’un message automatique et les réponses ne sont pas lues.
