<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Enums\ItemCategory;
use Modules\Inventory\Enums\ItemUnit;

class InventoryItem extends Model
{
    use HasUlids;

    protected $table = 'inventory';

    protected $fillable = [
        'name', 'code', 'category', 'unit', 'quantity', 'min_quantity',
        'unit_cost', 'sell_price', 'supplier_id', 'expiry_date', 'location', 'notes',
    ];

    protected $appends = ['unit_label', 'category_label'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'min_quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'expiry_date' => 'date',
        'category' => ItemCategory::class,
        'unit' => ItemUnit::class,
    ];

    public function getUnitLabelAttribute(): string
    {
        return $this->unit?->label() ?? '';
    }

    public function getCategoryLabelAttribute(): string
    {
        return $this->category?->label() ?? '';
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->min_quantity && $this->min_quantity > 0;
    }

    /**
     * Arabic letter variants that are routinely typed interchangeably
     * (different Hamza/Alef forms, Ta Marbuta vs Ha, Alef Maksura vs Ya),
     * plus the Tashkeel diacritics — folded away on both sides of the
     * comparison so inconsistent data entry doesn't hide real matches.
     *
     * @var array<string, string>
     */
    private const ARABIC_NORMALIZE_MAP = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ى' => 'ي', 'ئ' => 'ي',
        'ة' => 'ه',
        'ؤ' => 'و',
        'ً' => '', 'ٌ' => '', 'ٍ' => '', 'َ' => '', 'ُ' => '', 'ِ' => '', 'ّ' => '', 'ْ' => '',
    ];

    /** Match by item name or code, for the purchase-invoice and surgery-supply item autocompletes. */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = strtr($term, self::ARABIC_NORMALIZE_MAP);

        [$nameExpr, $nameBindings] = $this->normalizedColumnExpr('name');
        [$codeExpr, $codeBindings] = $this->normalizedColumnExpr('code');

        return $query->where(function (Builder $q) use ($term, $nameExpr, $nameBindings, $codeExpr, $codeBindings) {
            $q->whereRaw("{$nameExpr} LIKE ?", [...$nameBindings, "%{$term}%"])
                ->orWhereRaw("{$codeExpr} LIKE ?", [...$codeBindings, "%{$term}%"]);
        });
    }

    /**
     * Builds a `REPLACE(REPLACE(...))` SQL expression that folds the same
     * Arabic letter variants out of the given column, so it can be compared
     * against an already-normalized search term.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private function normalizedColumnExpr(string $column): array
    {
        $expr = $column;
        $bindings = [];

        foreach (self::ARABIC_NORMALIZE_MAP as $from => $to) {
            $expr = "REPLACE({$expr}, ?, ?)";
            $bindings[] = $from;
            $bindings[] = $to;
        }

        return [$expr, $bindings];
    }
}
