<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Gives every row of the `services` table a short unique code. A code typed by
 * the user is kept (upper-cased); otherwise "<DEPT>-0001" style codes are
 * generated per department. Shared by both Service models mapped to the table.
 *
 * @mixin Model
 */
trait HasServiceCode
{
    /** Department → code prefix used when a code is generated automatically. */
    public const CODE_PREFIXES = [
        'clinic' => 'CLN',
        'labs' => 'LAB',
        'surgery' => 'SRG',
        'lasik' => 'LSK',
        'laser' => 'LSR',
        'pentacam' => 'PNT',
    ];

    protected static function bootHasServiceCode(): void
    {
        static::creating(function (Model $service) {
            $service->code = filled($service->code)
                ? mb_strtoupper(trim($service->code))
                : static::nextCodeFor((string) $service->dept);
        });
    }

    public static function nextCodeFor(string $dept): string
    {
        $prefix = self::CODE_PREFIXES[$dept] ?? strtoupper(substr($dept, 0, 3));

        $last = static::query()
            ->where('code', 'like', "{$prefix}-%")
            ->pluck('code')
            ->map(fn (string $code) => (int) substr($code, strlen($prefix) + 1))
            ->max() ?? 0;

        return sprintf('%s-%04d', $prefix, $last + 1);
    }
}
