{{-- email-template
version: 1.0.0
language: en-us
category: authentication
subcategory: reset-password
format: html
layout: standard
subject: {{ $email_subject }}
description: Password reset link
--}}
@extends('emails.email_layout')

@section('content')
<!-- Start Content-->

<p>Hello{{ isset($attributes['name']) ? ' ' . $attributes['name'] : '' }},</p>

<p>You are receiving this email because we received a password reset request for your {{ config('app.name', 'Laravel') }} account.</p>

<p><a href="{{ $attributes['url'] ?? '' }}">Reset Password</a></p>

<p>This password reset link will expire in {{ $attributes['expire_minutes'] ?? '' }} minutes.</p>

<p>If you did not request a password reset, no further action is required.</p>

<p>If you have any questions, <a href="mailto:{{ $attributes["support_email"] ?? ''}}">email our customer success team</a>.</p>

<p>Stay secure, <br>
The {{ config('app.name', 'Laravel') }} Team</p>

@endsection
