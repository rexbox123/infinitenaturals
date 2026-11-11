<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Runtime store settings, edited in Admin → Settings / Homepage.
 * Values are JSON-encoded in the `settings` table and cached as one map.
 * Secrets (API keys, passwords) never belong here — they live in environment variables.
 */
class Settings
{
    private const CACHE_KEY = 'store.settings';

    /** Defaults come from the current infinitenaturals.com site where it states them. */
    public const DEFAULTS = [
        'store.name' => 'Infinite Naturals',
        'store.tagline' => 'An Essence of Natural Beauty',
        'store.footer_blurb' => 'An Essence of Natural Beauty. Lifting your natural beauty to infinity.',
        'store.email' => 'info@infinitenaturals.com',
        'store.phone' => '833-558-5503',
        'store.address' => '3850 SE 58th Ave, Ocala, FL 34480',
        'store.order_notification_email' => 'info@infinitenaturals.com',
        'store.logo_path' => null,
        'store.social_facebook' => null,
        'store.social_instagram' => null,

        'home.intro_title' => 'Natural Ingredients',
        'home.intro_body' => null,
        'home.mission_title' => 'Our Mission',
        'home.mission_body' => null,
        'home.featured_title' => 'Our Featured Products',
        'home.featured_subtitle' => null,
        'home.contact_title' => 'Contact Us',
        'home.contact_body' => null,

        'payments.stripe_enabled' => true,
        'payments.cod_enabled' => false,
        'payments.cod_instructions' => null,

        'checkout.require_terms' => true,
        'checkout.terms_page_slug' => 'terms-and-conditions',

        'inventory.low_stock_threshold' => 5,
    ];

    private ?array $values = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all();

        if (array_key_exists($key, $values) && $values[$key] !== null && $values[$key] !== '') {
            return $values[$key];
        }

        return $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
        $this->flush();
    }

    /** @param array<string, mixed> $values */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
        }
        $this->flush();
    }

    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        try {
            return $this->values = Cache::rememberForever(self::CACHE_KEY, function () {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                return Setting::query()->pluck('value', 'key')
                    ->map(fn ($v) => json_decode((string) $v, true))
                    ->all();
            });
        } catch (Throwable) {
            // Database not reachable yet (e.g. during the first deploy): fall back to defaults.
            return $this->values = [];
        }
    }

    public function flush(): void
    {
        $this->values = null;
        Cache::forget(self::CACHE_KEY);
    }
}
