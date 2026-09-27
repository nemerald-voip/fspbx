{{-- email-template
format: text
layout: none
--}}
Получен факс{{ $attributes['caller_id_number'] ? ' от ' . $attributes['caller_id_number'] : '' }}.

Получен новый факс для {{ $attributes['fax_destination'] }} и приложен к этому письму.

{{-- Domain: {{ $attributes['domain_name'] ?? '' }} --}}
От: {{ $attributes['caller_display'] }}
Кому: {{ $attributes['fax_destination'] }}
Страницы: {{ $attributes['fax_pages'] ?? '' }}
@if (!empty($attributes['fax_date']))
Получено: {{ $attributes['fax_date'] }}
@endif
{{-- Status: {{ $attributes['fax_result_text'] ?? '' }} --}}

Факс приложен к письму в формате {{ ($attributes['attachment_mime'] ?? '') === 'application/pdf' ? 'PDF' : 'TIFF' }}.

Есть вопросы? Напишите нашей службе поддержки:
{{ $attributes['support_email'] ?? '' }}

Спасибо,
Команда {{ config('app.name', 'Laravel') }}
