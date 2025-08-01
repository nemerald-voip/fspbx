@extends('emails.email_layout')

@section('content')
<!-- Start Content-->

<h1>New Archiving Storage Report</h1>
<p>Offload script executed successfully. Report is below.</p>
<!-- Action -->

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>Success:</strong>
            @if (isset($attributes['success'])) {{ count($attributes['success'])}} @else 0 @endif
          </td>
        </tr>
        @if (isset($attributes['failed']) && count($attributes['failed']) > 0)
        <tr>
          <td class="attributes_item"><strong>Failed:</strong>
              {{ count($attributes['failed'])}}
            </td>
        </tr>
        @endif
      </table>
    </td>
  </tr>
</table>

@if (isset($attributes['failed']) && count($attributes['failed']) > 0)

  @foreach ($attributes['failed'] as $rec)
    <li>{{ $rec['name'] }} -- Reason :: {{ $rec['msg'] }}</li>
  @endforeach

@endif

@endsection
