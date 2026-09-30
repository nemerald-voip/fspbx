{{-- email-template
version: 1.0.0
language: ru
category: export
subcategory: completed
format: html
layout: standard
subject: Ваш отчёт готов
description: Ваш отчёт готов
--}}
@extends('emails.ru.email_layout')

@section('content')
<!-- Start Content-->

<h1>Ваш отчёт готов.</h1>

<p>Запрошенный CSV-файл готов к скачиванию.</p>


<table class="action" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
    <td align="center">
    <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
    <td align="center">
    <table border="0" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
    <td>
        <a href="{{ $attributes['fileUrl'] }}" target="_blank" rel="noopener" style="display: inline-block; padding: 10px 20px; font-size: 16px; color: #ffffff; background-color: #4a90e2; border-radius: 5px; text-decoration: none;">
            Скачать отчёт
        </a>
    </td>
    </tr>
    </table>
    </td>
    </tr>
    </table>
    </td>
    </tr>
    </table>

<p>Спасибо!</p>

@endsection
