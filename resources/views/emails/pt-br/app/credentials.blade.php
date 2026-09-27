{{-- email-template
version: 1.0.0
language: pt-br
category: app
subcategory: credentials
format: html
layout: standard
subject: Credenciais do aplicativo {{ config('app.name', 'FS PBX') }}
description: Credenciais dos aplicativos móvel e de computador
--}}
@extends('emails.pt-br.email_layout')

@section('content')
<!-- Start Content-->

<p>Boas-vindas ao seu aplicativo {{ config('app.name', 'Laravel') }}. Guarde uma cópia deste e-mail para referência futura. Veja abaixo os passos para começar a usar o aplicativo:</p>
<p>1. Baixe o aplicativo para seus dispositivos:</p>
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
                      !important;" width="189" alt="Baixar para Android" data-proportionally-constrained="true" data-responsive="true"
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
                    text-decoration:none; font-family:Helvetica, arial, sans-serif; font-size:16px; height:auto !important;" width="174" alt="Baixar para iOS" data-proportionally-constrained="true" data-responsive="true"
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
                  ><span style="color:#ffffff;">Baixar para Windows</span></a>


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
                  ><span style="color:#ffffff;">Baixar para Mac</span></a>

                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

    </td>
  </tr>
</table>


<p>2. Após instalar e abrir o aplicativo, insira as credenciais abaixo ou leia o código QR:</p>
<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>Nome de exibição:</strong> {{ $attributes['name'] ?? ''}}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>Ramal do PBX:</strong> {{ $attributes['extension'] ?? ''}}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>
<p>Use estas credenciais para entrar:</p>
<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>Domínio:</strong> {{ $attributes['domain'] ?? ''}}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>Nome de Usuário:</strong> {{ $attributes['username'] ?? ''}}</td>
        </tr>
        @if(!empty($attributes['password_url']))
          <tr>
            <td class="attributes_item"><strong>Senha:</strong> <a href="{{ $attributes['password_url'] }}">Obter senha</a></td>
          </tr>
        @elseif(!empty($attributes['password']))
          <tr>
            <td class="attributes_item"><strong>Senha:</strong> {{ $attributes['password'] }}</td>
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
          alt="Código QR das credenciais do aplicativo móvel"
          width="180"
          style="display:block; width:180px; height:auto; margin:0 auto;"
        >
      </td>
    </tr>
  @endif
</table>

<p>3. Após entrar, você poderá se comunicar com os usuários da sua organização: fazer e receber chamadas pelo seu ramal, colocá-las em espera, transferi-las, estacioná-las e muito mais.</p>

<p>Se você tiver alguma dúvida, <a href="mailto:{{ $attributes["support_email"] ?? ''}}">escreva para nossa equipe de atendimento ao cliente</a>. (Respondemos rapidamente.)</p>
<p>Obrigado,
  <br>A equipe de {{ config('app.name', 'Laravel') }}</p>
<p><strong>P.S.</strong> Precisa de ajuda para começar? A equipe de suporte de {{ config('app.name', 'Laravel') }} está sempre pronta para ajudar! Basta responder a este e-mail.</p>

@endsection
