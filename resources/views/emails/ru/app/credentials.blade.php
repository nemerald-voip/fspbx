{{-- email-template
version: 1.0.0
language: ru
category: app
subcategory: credentials
format: html
layout: standard
subject: Данные для входа в приложение {{ config('app.name', 'FS PBX') }}
description: Данные для входа в мобильное и настольное приложение
--}}
@extends('emails.ru.email_layout')

@section('content')
<!-- Start Content-->

<p>Добро пожаловать в приложение {{ config('app.name', 'Laravel') }}. Сохраните это письмо для дальнейшего использования. Ниже приведены простые шаги для начала работы:</p>
<p>1. Скачайте приложение для своих устройств:</p>
<!-- Action -->
<table class="body-action" align="center" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td align="center">
      <!-- Border based button https://litmus.com/blog/a-guide-to-bulletproof-buttons-in-email-design -->
      <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td align="center">
            <table border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td>

                  <a href="{{ $attributes['google_play_link'] ?? '' }}">
                    <img class="max-width" border="0" style="display:block; color:#000000; text-decoration:none; font-family:Helvetica, arial, sans-serif; font-size:16px; height:auto
                      !important;" width="189" alt="Скачать для Android" data-proportionally-constrained="true" data-responsive="true"
                      src="https://cdn.mcauto-images-production.sendgrid.net/b9e58e76174a4c84/88af7fc9-c74b-43ec-a1e2-a712cd1d3052/646x250.png">
                  </a>


                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
    <td>
      <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td align="center">
            <table border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td>
                  <a href="{{ $attributes['apple_store_link'] ?? '' }}"><img class="max-width" border="0" style="display:block; color:#000000;
                    text-decoration:none; font-family:Helvetica, arial, sans-serif; font-size:16px; height:auto !important;" width="174" alt="Скачать для iOS" data-proportionally-constrained="true" data-responsive="true"
                    src="https://cdn.mcauto-images-production.sendgrid.net/b9e58e76174a4c84/bb2daef8-a40d-4eed-8fb4-b4407453fc94/320x95.png">
                  </a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

    </td>
  </tr>
  <tr>
    <td align="center">
      <!-- Border based button https://litmus.com/blog/a-guide-to-bulletproof-buttons-in-email-design -->
      <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td align="center">
            <table border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td>
                  <a
                    href="{{ $attributes['windows_link'] ?? '' }}"
                    target="_blank"
                    style="background-color:#3869D4; border-top:10px solid #3869D4; border-right:18px solid #3869D4; border-bottom:10px solid #3869D4; border-left:18px solid #3869D4; border-radius:3px; color:#ffffff !important; display:inline-block; font-family:Helvetica, Arial, sans-serif; font-size:16px; font-weight:700; line-height:20px; text-align:center; text-decoration:none; -webkit-text-size-adjust:none;"
                  ><span style="color:#ffffff;">Скачать для Windows</span></a>


                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
    <td>
      <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td align="center">
            <table border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td>
                  <a
                    href="{{ $attributes['mac_link'] ?? '' }}"
                    target="_blank"
                    style="background-color:#3869D4; border-top:10px solid #3869D4; border-right:18px solid #3869D4; border-bottom:10px solid #3869D4; border-left:18px solid #3869D4; border-radius:3px; color:#ffffff !important; display:inline-block; font-family:Helvetica, Arial, sans-serif; font-size:16px; font-weight:700; line-height:20px; text-align:center; text-decoration:none; -webkit-text-size-adjust:none;"
                  ><span style="color:#ffffff;">Скачать для Mac</span></a>

                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

    </td>
  </tr>
</table>


<p>2. После установки и запуска приложения введите указанные ниже данные или отсканируйте QR-код:</p>
<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>Отображаемое имя:</strong> {{ $attributes['name'] ?? ''}}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>Внутренний номер АТС:</strong> {{ $attributes['extension'] ?? ''}}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>
<p>Используйте эти данные для входа:</p>
<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>Домен:</strong> {{ $attributes['domain'] ?? ''}}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>Имя пользователя:</strong> {{ $attributes['username'] ?? ''}}</td>
        </tr>
        @if(!empty($attributes['password_url']))
          <tr>
            <td class="attributes_item"><strong>Пароль:</strong> <a href="{{ $attributes['password_url'] }}">Получить пароль</a></td>
          </tr>
        @elseif(!empty($attributes['password']))
          <tr>
            <td class="attributes_item"><strong>Пароль:</strong> {{ $attributes['password'] }}</td>
          </tr>
        @endif
      </table>
    </td>
  </tr>
  @if(empty($attributes['password_url']) && !empty($attributes['qrCodeUrl']))
    <tr>
      <td class="attributes_content" align="center" style="padding-top: 0;">
        <img
          src="{{ $attributes['qrCodeUrl'] }}"
          alt="QR-код для входа в мобильное приложение"
          width="180"
          style="display:block; width:180px; height:auto; margin:0 auto;"
        >
      </td>
    </tr>
  @endif
</table>

<p>3. После входа вы сможете общаться с сотрудниками своей организации: совершать и принимать звонки через свой внутренний номер, ставить вызовы на удержание, переводить и парковать их и многое другое.</p>

<p>Если у вас есть вопросы, <a href="mailto:{{ $attributes["support_email"] ?? ''}}">напишите нашей службе поддержки</a>. (Мы отвечаем очень быстро.)</p>
<p>Спасибо,
  <br>Команда {{ config('app.name', 'Laravel') }}</p>
<p><strong>P.S.</strong> Нужна помощь с началом работы? Служба поддержки {{ config('app.name', 'Laravel') }} всегда готова помочь! Просто ответьте на это письмо.</p>

@endsection
