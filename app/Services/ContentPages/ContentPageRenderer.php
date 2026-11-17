<?php

namespace App\Services\ContentPages;

use Illuminate\Support\Str;

/**
 * Turns a content page's Markdown into safe HTML. Raw HTML in the Markdown
 * is stripped and unsafe links (javascript: and similar) are removed, so the
 * result can be rendered directly.
 */
class ContentPageRenderer
{
    /**
     * The business details shown on public pages, from config/trippilot.php.
     *
     * @return array{business_name: string, support_email: string|null, support_phone: string|null, address: string|null, jurisdiction: string}
     */
    public function businessDetails(): array
    {
        return [
            'business_name' => (string) config('trippilot.legal.business_name'),
            'support_email' => config('trippilot.legal.support_email'),
            'support_phone' => config('trippilot.legal.support_phone'),
            'address' => config('trippilot.legal.address'),
            'jurisdiction' => (string) config('trippilot.legal.jurisdiction'),
        ];
    }

    /**
     * Placeholders admins can use in a page body, with what each one shows.
     *
     * @return list<array{token: string, value: string}>
     */
    public function placeholders(): array
    {
        return collect($this->businessDetails())
            ->map(fn (?string $value, string $key): array => [
                'token' => '{'.$key.'}',
                'value' => (string) $value,
            ])
            ->values()
            ->all();
    }

    public function toHtml(string $markdown): string
    {
        $values = collect($this->placeholders())
            ->mapWithKeys(fn (array $placeholder): array => [$placeholder['token'] => $placeholder['value']])
            ->all();

        // Markdown editors may write {business\_name}; match it either way.
        $filled = preg_replace_callback(
            '/\{([a-z_\\\\]+)\}/',
            fn (array $match): string => $values['{'.str_replace('\\', '', $match[1]).'}'] ?? $match[0],
            $markdown,
        );

        return Str::markdown($filled, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
