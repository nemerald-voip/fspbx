@extends('emails.email_layout')

@section('content')
<!-- Start Content-->

<h1>Welcome, {{ $name ?? ''}}!</h1>
<p>Thanks for trying {{ $product_name ?? ''}}. We’re thrilled to have you on board. To get the most out of {{ $product_name ?? ''}}, do this primary next step:</p>
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
                  <a href="{{ $action_url ?? ''}}" class="button button--" target="_blank">Do this Next</a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
<p>For reference, here's your login information:</p>
<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>Login Page:</strong> {{ $login_url ?? ''}}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>Username:</strong> {{ $username ?? ''}}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>
<p>You've started a {{ $trial_length ?? ''}} day trial. You can upgrade to a paying account or cancel any time.</p>
<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>Trial Start Date:</strong> {{ $trial_start_date ?? ''}}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>Trial End Date:</strong> {{ $trial_end_date ?? ''}}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>
<p>If you have any questions, feel free to <a href="mailto:{{ $support_email ?? ''}}">email our customer success team</a>. (We're lightning quick at replying.) We also offer <a href="{{ $live_chat_url ?? ''}}">live chat</a> during business hours.</p>
<p>Thanks,
  <br>{{ $sender_name ?? '' }} and the {{ $product_name ?? ''}} Team</p>
<p><strong>P.S.</strong> Need immediate help getting started? Check out our <a href="{{ $help_url ?? ''}}">help documentation</a>. Or, just reply to this email, the {{ $product_name ?? ''}} support team is always ready to help!</p>
<!-- Sub copy -->
<table class="body-sub">
  <tr>
    <td>
      <p class="sub">If you’re having trouble with the button above, copy and paste the URL below into your web browser.</p>
      <p class="sub">{{ $action_url ?? ''}}</p>
    </td>
  </tr>
</table>


@endsection