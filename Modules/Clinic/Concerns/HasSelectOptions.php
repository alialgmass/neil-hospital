<?php

namespace Modules\Clinic\Concerns;

/**
 * Shapes a backed enum's cases as `{value, label}` pairs for Vue selects
 * and checkbox groups. The enum must define a `label(): string` method.
 */
trait HasSelectOptions
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
