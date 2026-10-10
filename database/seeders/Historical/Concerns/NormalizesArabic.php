<?php

namespace Database\Seeders\Historical\Concerns;

/**
 * Normalizes Arabic text so doctor/service name matching is resilient to
 * common spelling variants: ي/ى, ة/ه, tashkeel (تشكيل), and whitespace.
 */
trait NormalizesArabic
{
    protected function normalizeArabic(string $value): string
    {
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $value) ?? $value;
        $value = str_replace(['أ', 'إ', 'آ'], 'ا', $value);
        $value = str_replace(['ى', 'ي'], 'ي', $value);
        $value = str_replace(['ة', 'ه'], 'ه', $value);
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return mb_strtolower($value, 'UTF-8');
    }
}
