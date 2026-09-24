<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot of one of the active platform discount tiers at the moment an
 * order was placed (see ASSUMPTIONS.md #14) — never updated afterward.
 */
#[Fillable(['order_id', 'min_order_amount', 'discount_percent'])]
class OrderPlatformDiscountTier extends Model
{
    protected function casts(): array
    {
        return [
            'min_order_amount' => 'decimal:2',
            'discount_percent' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
