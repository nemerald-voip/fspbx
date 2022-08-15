@extends('emails.email_layout')

@section('content')
<!-- Start Content-->

<h1>New Archiving Storage Report</h1>
<p>Offload finished with status: Success</p>
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
        <tr>
          <td class="attributes_item"><strong>Failed:</strong> 
            @if (isset($attributes['failed'])) {{ count($attributes['failed'])}} @else 0 @endif
        </tr>
      </table>
    </td>
  </tr>
</table>

@if (isset($failed))

  @foreach ($attributes['failed'] as $rec)
    <li>{{ $rec['name'] }} -- Reason :: {{ $rec['msg'] }}</li>
  @endforeach

@endif

@endsection