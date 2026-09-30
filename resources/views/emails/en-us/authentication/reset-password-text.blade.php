{{-- email-template
format: text
layout: none
--}}
Hello{{ isset($attributes['name']) ? ' ' . $attributes['name'] : '' }},

You are receiving this email because we received a password reset request for your {{ config('app.name', 'Laravel') }} account.

Reset your password: {{ $attributes['url'] ?? '' }}

This password reset link will expire in {{ $attributes['expire_minutes'] ?? '' }} minutes.

If you did not request a password reset, no further action is required.

If you have any questions, email our customer success team at {{ $attributes['support_email'] ?? '' }}.

Stay secure,
The {{ config('app.name', 'Laravel') }} Team

Do not reply to this email as it is an automated message and responses are not monitored.
