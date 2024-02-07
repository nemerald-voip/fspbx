Welcome to your {{ config('app.name', 'Laravel') }} app. Make sure to keep a copy of this email for future reference. Below are the simple steps to get started using the app:

Download the app for your device(s):

Google Play: {{ $attributes['google_play_link'] ?? '' }}
Apple Store: {{ $attributes['apple_store_link'] ?? '' }}
Get it for Windows ({{ $attributes['windows_link'] ?? '' }})
Download for Mac ({{ $attributes['mac_link'] ?? '' }})

Display name: {{ $attributes['name'] ?? ''}}
PBX Extension: {{ $attributes['extension'] ?? ''}}

Use these credentials to log in:

Domain: {{ $attributes['domain'] ?? ''}}
Username: {{ $attributes['username'] ?? ''}}
Password: {{ $attributes['password'] ?? ''}}

Once you have logged in, start communicating with the users within your organization. You can make and receive phone calls through your extension, put calls on hold, transfer calls, park calls, and much more.

If you have any questions, email our customer success team at {{ $attributes['support_email'] ?? '' }} . (We are lightning quick at replying.)

Thanks,
{{ config('app.name', 'Laravel') }} Team

P.S. Need immediate help getting started? The {{ config('app.name', 'Laravel') }} support team is always ready to help! Just reply to this email.