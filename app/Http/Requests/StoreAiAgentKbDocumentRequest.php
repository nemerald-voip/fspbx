<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAiAgentKbDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_type' => 'required|in:file,url,text',
            'name'          => 'required|string|max:255',
            'file'          => 'required_if:document_type,file|file|mimes:pdf,txt,docx,html,htm,epub,md|max:20480',
            'url'           => 'required_if:document_type,url|nullable|url|max:2048',
            'text'          => 'required_if:document_type,text|nullable|string|max:50000',
        ];
    }
}
