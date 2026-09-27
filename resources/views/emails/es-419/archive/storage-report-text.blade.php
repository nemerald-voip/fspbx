{{-- email-template
format: text
layout: none
--}}
Nuevo informe de almacenamiento de archivos

El script de transferencia finalizó.

Servidor: {{ $attributes['hostname'] ?? 'desconocido' }}
Correctos: {{ isset($attributes['success']) ? count($attributes['success']) : 0 }}
Falló: {{ isset($attributes['failed']) ? count($attributes['failed']) : 0 }}

@if(isset($attributes['failed']) && count($attributes['failed']) > 0)
Registros con errores:
@foreach($attributes['failed'] as $record)
- {{ $record['name'] }} — {{ $record['msg'] }}
@endforeach
@endif
