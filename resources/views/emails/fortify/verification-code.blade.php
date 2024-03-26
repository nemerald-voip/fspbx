@extends('emails.email_layout')

@section('content')
<!-- Start Content-->

<p>Hello{{ isset($attributes['name']) ? ' ' . $attributes['name'] : '' }},</p>
<p>Use the code below to complete your authentication:</p>
<p>Your 2FA Code: <b>{{ $attributes['code'] ?? '' }}</b></p>


<p>Please use the above code to complete the sign-in process for your Nemerald account. 
    This extra step confirms it's really you trying to access your account.</p>


<p><b>Why are you receiving this email?</b></p>

<p>This security measure is triggered whenever 2FA is enabled or a sign-in attempt is made. 
If you initiated this action, use the code to proceed. If you did not request this code, 
no action is required on your part—without the code, access to your account remains secure.</p>

<p><b>Did not request this code?</b></p>

<p>If you didn't request this code or suspect any unauthorized activity, please secure 
    your account immediately by changing your password and contacting our support team.</p>

<p>If you have any questions, <a href="mailto:{{ $attributes["support_email"] ?? ''}}">email our customer success team</a>.</p>

<p>Stay secure, <br>
The {{ config('app.name', 'Laravel') }} Team</p>

<p><strong>P.S.</strong> Need immediate help getting started? The {{ config('app.name', 'Laravel') }} support team is always ready to help! Just reply to this email.</p>

@endsection