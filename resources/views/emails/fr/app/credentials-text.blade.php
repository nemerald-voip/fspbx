{{-- email-template
format: text
layout: none
--}}
Bienvenue dans votre application {{ config('app.name', 'Laravel') }}. Conservez une copie de cet e-mail pour référence. Voici les étapes pour commencer à utiliser l’application :

Téléchargez l’application pour vos appareils :

Google Play : {{ $attributes['google_play_link'] ?? '' }}
Apple Store : {{ $attributes['apple_store_link'] ?? '' }}
Télécharger pour Windows ({{ $attributes['windows_link'] ?? '' }})
Télécharger pour Mac ({{ $attributes['mac_link'] ?? '' }})

Nom affiché : {{ $attributes['name'] ?? ''}}
Poste du PBX : {{ $attributes['extension'] ?? ''}}

Utilisez ces identifiants pour vous connecter :

Domaine : {{ $attributes['domain'] ?? ''}}
Nom d'utilisateur : {{ $attributes['username'] ?? ''}}
@if(!empty($attributes['password_url']))
Mot de passe : {{ $attributes['password_url'] }}
@elseif(!empty($attributes['password']))
Mot de passe : {{ $attributes['password'] }}
@endif

Une fois connecté, vous pouvez communiquer avec les utilisateurs de votre organisation : passer et recevoir des appels via votre poste, les mettre en attente, les transférer, les parquer et bien plus encore.

Si vous avez des questions, contactez notre équipe d’assistance à l’adresse {{ $attributes['support_email'] ?? '' }}. (Nous répondons très rapidement.)

Merci,
L’équipe {{ config('app.name', 'Laravel') }}

P.-S. Besoin d’aide pour démarrer ? L’équipe d’assistance de {{ config('app.name', 'Laravel') }} est toujours prête à vous aider ! Répondez simplement à cet e-mail.
