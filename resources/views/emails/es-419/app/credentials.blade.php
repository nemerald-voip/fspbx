{{-- email-template
version: 1.0.0
language: es-419
category: app
subcategory: credentials
format: html
layout: standard
subject: Credenciales de la aplicación {{ config('app.name', 'FS PBX') }}
description: Credenciales de las aplicaciones móvil y de escritorio
--}}
@extends('emails.es-419.email_layout')

@section('content')
<!-- Start Content-->

<p>Te damos la bienvenida a tu aplicación {{ config('app.name', 'Laravel') }}. Guarda una copia de este correo para consultarlo después. Sigue estos sencillos pasos para comenzar a usar la aplicación:</p>
<p>1. Descarga la aplicación para tus dispositivos:</p>
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
                      !important;" width="189" alt="Descargar para Android" data-proportionally-constrained="true" data-responsive="true"
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
                    text-decoration:none; font-family:Helvetica, arial, sans-serif; font-size:16px; height:auto !important;" width="174" alt="Descargar para iOS" data-proportionally-constrained="true" data-responsive="true"
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
                  ><span style="color:#ffffff;">Descargar para Windows</span></a>


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
                  ><span style="color:#ffffff;">Descargar para Mac</span></a>

                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

    </td>
  </tr>
</table>


<p>2. Después de instalar y abrir la aplicación, ingresa las credenciales de abajo o escanea el código QR:</p>
<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>Nombre para mostrar:</strong> {{ $attributes['name'] ?? ''}}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>Extensión de PBX:</strong> {{ $attributes['extension'] ?? ''}}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>
<p>Usa estas credenciales para iniciar sesión:</p>
<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>Dominio:</strong> {{ $attributes['domain'] ?? ''}}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>Usuario:</strong> {{ $attributes['username'] ?? ''}}</td>
        </tr>
        @if(!empty($attributes['password_url']))
          <tr>
            <td class="attributes_item"><strong>Contraseña:</strong> <a href="{{ $attributes['password_url'] }}">Obtener contraseña</a></td>
          </tr>
        @elseif(!empty($attributes['password']))
          <tr>
            <td class="attributes_item"><strong>Contraseña:</strong> {{ $attributes['password'] }}</td>
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
          alt="Código QR de las credenciales de la aplicación móvil"
          width="180"
          style="display:block; width:180px; height:auto; margin:0 auto;"
        >
      </td>
    </tr>
  @endif
</table>

<p>3. Después de iniciar sesión, puedes comunicarte con los usuarios de tu organización: hacer y recibir llamadas desde tu extensión, ponerlas en espera, transferirlas, estacionarlas y mucho más.</p>

<p>Si tienes alguna pregunta, <a href="mailto:{{ $attributes["support_email"] ?? ''}}">escribe a nuestro equipo de atención al cliente</a>. (Respondemos muy rápido.)</p>
<p>Gracias,
  <br>El equipo de {{ config('app.name', 'Laravel') }}</p>
<p><strong>P. D.</strong> ¿Necesitas ayuda para comenzar? El equipo de soporte de {{ config('app.name', 'Laravel') }} siempre está listo para ayudarte. Solo responde a este correo.</p>

@endsection
