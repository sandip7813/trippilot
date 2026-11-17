<?php

namespace App\Models;

use Database\Factories\ContentPageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * A public content page (Terms, Privacy, About…) edited by admins. The body
 * is Markdown and may contain business-detail placeholders such as
 * {business_name}, which are filled in when the page is shown.
 *
 * System pages are the policies payment providers review: they keep their
 * own URLs (/terms, /privacy, /refund-policy), are always published and
 * cannot be deleted.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $description
 * @property string $body
 * @property bool $is_system
 * @property bool $is_published
 * @property bool $show_in_footer
 * @property int $sort_order
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ContentPage extends Model
{
    /** @use HasFactory<ContentPageFactory> */
    use HasFactory;

    public const string FOOTER_CACHE_KEY = 'content_pages.footer_links';

    /**
     * Route names of the system pages, keyed by slug.
     *
     * @var array<string, string>
     */
    public const array SYSTEM_ROUTES = [
        'terms' => 'terms',
        'privacy' => 'privacy',
        'refund-policy' => 'refund-policy',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'title',
        'description',
        'body',
        'is_published',
        'show_in_footer',
        'sort_order',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::FOOTER_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::FOOTER_CACHE_KEY));
    }

    /**
     * Published pages marked for the footer, in display order.
     *
     * @return list<array{title: string, href: string}>
     */
    public static function footerLinks(): array
    {
        return Cache::rememberForever(self::FOOTER_CACHE_KEY, fn (): array => self::query()
            ->published()
            ->where('show_in_footer', true)
            ->ordered()
            ->get()
            ->map(fn (ContentPage $page): array => [
                'title' => $page->title,
                'href' => $page->url(),
            ])
            ->all());
    }

    /**
     * The page's public path.
     */
    public function url(): string
    {
        $routeName = self::SYSTEM_ROUTES[$this->slug] ?? null;

        if ($this->is_system && $routeName !== null) {
            return route($routeName, absolute: false);
        }

        return route('pages.show', $this->slug, absolute: false);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @param  Builder<ContentPage>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * @param  Builder<ContentPage>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('title');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_published' => 'boolean',
            'show_in_footer' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
