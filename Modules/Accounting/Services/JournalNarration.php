<?php

namespace Modules\Accounting\Services;

use BackedEnum;
use Illuminate\Support\Str;

/**
 * Builds the detailed Arabic "البيان" (narration) for journal and treasury
 * entries in one consistent shape:
 *
 *   {العملية} — {التسمية}: {القيمة} — {التسمية}: {القيمة} …
 *
 * Empty details are skipped so callers can pass optional values freely.
 */
final class JournalNarration
{
    private const MAX_LENGTH = 300;

    /**
     * @param  array<string, string|int|float|BackedEnum|null>  $details  label => value
     */
    public static function make(string $title, array $details = []): string
    {
        $parts = [$title];

        foreach ($details as $label => $value) {
            $text = self::stringify($value);

            if ($text !== '') {
                $parts[] = "{$label}: {$text}";
            }
        }

        return Str::limit(implode(' — ', $parts), self::MAX_LENGTH, '…');
    }

    public static function money(float|int|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', ',').' ج';
    }

    private static function stringify(string|int|float|BackedEnum|null $value): string
    {
        if ($value instanceof BackedEnum) {
            return method_exists($value, 'label') ? (string) $value->label() : (string) $value->value;
        }

        return trim((string) $value);
    }
}
