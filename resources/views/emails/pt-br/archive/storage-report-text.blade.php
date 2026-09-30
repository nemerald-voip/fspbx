{{-- email-template
format: text
layout: none
--}}
Novo relatório de armazenamento de arquivos

O script de transferência foi concluído.

Servidor: {{ $attributes['hostname'] ?? 'desconhecido' }}
Concluídos: {{ isset($attributes['success']) ? count($attributes['success']) : 0 }}
Falhou: {{ isset($attributes['failed']) ? count($attributes['failed']) : 0 }}

@if(isset($attributes['failed']) && count($attributes['failed']) > 0)
Registros com falha:
@foreach($attributes['failed'] as $record)
- {{ $record['name'] }} — {{ $record['msg'] }}
@endforeach
@endif
