<?php

namespace App\Http\Requests\Admin;

use App\Enums\KnowledgeDocumentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKnowledgeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'destinations' => ['required', 'string', 'max:500'],
            'content' => ['required', 'string', 'max:50000'],
            'status' => ['required', Rule::enum(KnowledgeDocumentStatus::class)],
        ];
    }
}
