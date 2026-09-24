<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_item_id', 'store_id', 'quantity', 'returned_quantity', 'distance_km'])]
class OrderItemAllocation extends Model
{
    protected function casts(): array
    {
        return [
            'distance_km' => 'decimal:3',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function remainingQuantity(): int
    {
        return $this->quantity - $this->returned_quantity;
    }
}
