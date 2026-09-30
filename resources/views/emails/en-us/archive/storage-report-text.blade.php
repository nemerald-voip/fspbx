{{-- email-template
format: text
layout: none
--}}
New Archiving Storage Report

The offload script completed.

Server: {{ $attributes['hostname'] ?? 'unknown' }}
Successful: {{ isset($attributes['success']) ? count($attributes['success']) : 0 }}
Failed: {{ isset($attributes['failed']) ? count($attributes['failed']) : 0 }}

@if(isset($attributes['failed']) && count($attributes['failed']) > 0)
Failed records:
@foreach($attributes['failed'] as $record)
- {{ $record['name'] }} — {{ $record['msg'] }}
@endforeach
@endif
