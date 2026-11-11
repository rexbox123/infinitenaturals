<?php

use App\Services\Settings;
use App\Support\Money;

if (! function_exists('money')) {
    /** Format integer cents as a currency string, e.g. 6900 → "$69.00". */
    function money(?int $cents): string
    {
        return Money::format($cents ?? 0);
    }
}

if (! function_exists('setting')) {
    /** Read a store setting managed in Admin → Settings. */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(Settings::class)->get($key, $default);
    }
}
