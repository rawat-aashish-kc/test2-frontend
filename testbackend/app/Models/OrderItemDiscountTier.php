<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot of one of a product's active discount tiers at the moment an
 * order was placed (see ASSUMPTIONS.md #14) — never updated afterward.
 */
#[Fillable(['order_item_id', 'min_quantity', 'discount_percent'])]
class OrderItemDiscountTier extends Model
{
    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
