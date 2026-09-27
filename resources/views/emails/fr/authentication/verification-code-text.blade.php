{{-- email-template
format: text
layout: none
--}}
Bonjour{{ isset($attributes['name']) ? ' ' . $attributes['name'] : '' }},

Utilisez le code ci-dessous pour terminer votre authentification :

Votre code de double authentification : {{ $attributes['code'] ?? '' }}

Utilisez le code ci-dessus pour vous connecter à votre compte {{ config('app.name', 'Laravel') }}.
Cette étape supplémentaire confirme que c’est bien vous qui tentez d’accéder à votre compte.

Pourquoi recevez-vous cet e-mail ?
Cette mesure de sécurité s’applique lorsque l’authentification à deux facteurs est activée ou lors d’une tentative de connexion.
Si vous êtes à l’origine de cette action, utilisez le code pour continuer. Sinon,
aucune action n’est nécessaire : sans le code, l’accès à votre compte reste protégé.

Vous n’avez pas demandé ce code ?
Si vous n’avez pas demandé ce code ou soupçonnez une activité non autorisée, sécurisez
immédiatement votre compte en changeant votre mot de passe et en contactant notre équipe d’assistance.

Si vous avez des questions, contactez notre équipe d’assistance à l’adresse {{ $attributes['support_email'] ?? '' }}.

Prenez soin de votre sécurité,
L’équipe {{ config('app.name', 'Laravel') }}

P.-S. Besoin d’aide pour démarrer ? L’équipe d’assistance de {{ config('app.name', 'Laravel') }} est toujours prête à vous aider ! Répondez simplement à cet e-mail.

Ne répondez pas à cet e-mail : il s’agit d’un message automatique et les réponses ne sont pas lues.
