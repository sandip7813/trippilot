<?php

namespace App\Http\Requests\Admin;

use App\Models\ContentPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * System pages (Terms, Privacy, Refund policy) keep their address and stay
 * published, so their slug and published flag are ignored.
 */
class UpdateContentPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ContentPage $contentPage */
        $contentPage = $this->route('contentPage');

        return [
            'title' => ['required', 'string', 'max:120'],
            'slug' => [
                Rule::excludeIf($contentPage->is_system),
                'required',
                'string',
                'max:80',
                'regex:'.StoreContentPageRequest::SLUG_PATTERN,
                Rule::unique('content_pages', 'slug')->ignore($contentPage),
            ],
            'description' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:100000'],
            'is_published' => [Rule::excludeIf($contentPage->is_system), 'boolean'],
            'show_in_footer' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Use lowercase letters, numbers and single hyphens only, e.g. about-us.',
        ];
    }
}
