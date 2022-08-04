@extends('emails.email_layout')

@section('content')
<!-- Start Content-->

<p>Welcome to your {{ config('app.name', 'Laravel') }} app. Make sure to keep a copy of this email for future reference. Below are the simple steps to get started using the app:</p>
<p>1. Download the app for your device(s):</p>
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

                  <a href="https://play.google.com/store/apps/details?idsmile=.com.nemerald.mobile">
                    <img class="max-width" border="0" style="display:block; color:#000000; text-decoration:none; font-family:Helvetica, arial, sans-serif; font-size:16px; height:auto 
                      !important;" width="189" alt="Download for Android" data-proportionally-constrained="true" data-responsive="true" 
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
                  <a href="https://apps.apple.com/app/nemerald/id1607586125"><img class="max-width" border="0" style="display:block; color:#000000; 
                    text-decoration:none; font-family:Helvetica, arial, sans-serif; font-size:16px; height:auto !important;" width="174" alt="Download for iOS" data-proportionally-constrained="true" data-responsive="true" 
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
                  <a href="{{ $action_url ?? ''}}" class="button button--" target="_blank">Get it for <strong>Windows</strong></a>
                  {{-- <a href="https://apps.nemerald.com/Windows" style="border-radius:6px; border-width:1px; display:inline-block; font-weight:normal; 
                    letter-spacing:0px; padding:14px 14px 14px 14px; text-align:center; text-decoration:none; border-style:solid; font-size:16px; line-height:normal; 
                    background-color:#ffffff; color:#333333; border:1px solid #333333; border-color:#333333;" target="_blank">Get it for <strong>Windows</strong>
                  </a> --}}

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
                  <a href="{{ $action_url ?? ''}}" class="button button--" target="_blank">Download for <strong>Mac</strong></a>
                  {{-- <a href="https://apps.nemerald.com/Windows" style="border-radius:6px; border-width:1px; display:inline-block; font-weight:normal; 
                    letter-spacing:0px; padding:14px 14px 14px 14px; text-align:center; text-decoration:none; border-style:solid; font-size:16px; line-height:normal; 
                    background-color:#ffffff; color:#333333; border:1px solid #333333; border-color:#333333;" target="_blank">Download for <strong>Mac</strong>
                  </a> --}}
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

    </td>
  </tr>
</table>


<p>2. Once you have installed and launched the app, enter the credentials below or scan the QR code:</p>
<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>Display name:</strong> {{ $attributes['name'] ?? ''}}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>PBX Extension:</strong> {{ $attributes['extension'] ?? ''}}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>
<p>Use these credentials to log in:</p>
<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>Domain:</strong> {{ $attributes['domain'] ?? ''}}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>Username:</strong> {{ $attributes['username'] ?? ''}}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>Password:</strong> {{ $attributes['password'] ?? ''}}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<p>3. Once you have logged in, start communicating with the users within your organization. You can make and receive phone calls through your extension, put calls on hold, transfer calls, park calls, and much more.</p>

<p>If you have any questions, feel free to <a href="mailto:{{ $support_email ?? ''}}">email our customer success team</a>. (We're lightning quick at replying.)</p>
<p>Thanks,
  <br>{{ config('app.name', 'Laravel') }} Team</p>
<p><strong>P.S.</strong> Need immediate help getting started? Check out our <a href="{{ $help_url ?? ''}}">help documentation</a>. Or, just reply to this email, the {{ config('app.name', 'Laravel') }} support team is always ready to help!</p>

@endsection