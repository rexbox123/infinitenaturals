<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money is stored as integer cents everywhere to avoid floating point errors.
 */
final class Money
{
    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';

        return $sign.'$'.number_format(abs($cents) / 100, 2);
    }

    /** Parse user input such as "69", "69.5" or "$1,069.00" into cents. */
    public static function toCents(string|int|float|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = str_replace([',', '$', ' '], '', (string) $value);

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $clean)) {
            throw new InvalidArgumentException("Invalid amount [{$value}].");
        }

        [$whole, $fraction] = array_pad(explode('.', $clean), 2, '0');

        return ((int) $whole) * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    /** Cents → "69.00" for form inputs. */
    public static function toDecimal(?int $cents): string
    {
        return $cents === null ? '' : number_format($cents / 100, 2, '.', '');
    }
}
