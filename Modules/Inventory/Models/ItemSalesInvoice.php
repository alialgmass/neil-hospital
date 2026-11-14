<?php

namespace Modules\Inventory\Models;

use App\Enums\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Booking\Enums\PayMethod;

/**
 * A point-of-sale invoice for inventory items sold directly to a patient
 * or walk-in customer (drops, glasses, ...) at their sell price.
 */
class ItemSalesInvoice extends Model
{
    use HasUlids;

    protected $fillable = [
        'invoice_no',
        'invoice_date',
        'customer_name',
        'customer_phone',
        'file_no',
        'department',
        'pay_method',
        'subtotal',
        'discount',
        'total',
        'cost_total',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'department' => Department::class,
        'pay_method' => PayMethod::class,
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'cost_total' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ItemSalesInvoiceItem::class, 'invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
