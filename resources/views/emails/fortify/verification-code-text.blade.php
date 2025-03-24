Hello{{ isset($attributes['name']) ? ' ' . $attributes['name'] : '' }},

Use the code below to complete your authentication:

Your 2FA Code: {{ $attributes['code'] ?? '' }}

Please use the above code to complete the sign-in process for your {{ config('app.name', 'Laravel') }} account. 
This extra step confirms it's really you trying to access your account.

Why are you receiving this email?
This security measure is triggered whenever 2FA is enabled or a sign-in attempt is made. 
If you initiated this action, use the code to proceed. If you did not request this code, 
no action is required on your part—without the code, access to your account remains secure.

Did not request this code?
If you didn't request this code or suspect any unauthorized activity, please secure 
your account immediately by changing your password and contacting our support team.

If you have any questions, email our customer success team at {{ $attributes['support_email'] ?? '' }}.

Stay secure,
The {{ config('app.name', 'Laravel') }} Team

P.S. Need immediate help getting started? The {{ config('app.name', 'Laravel') }} support team is always ready to help! Just reply to this email.

Do not reply to this email as it is an automated message and responses are not monitored.