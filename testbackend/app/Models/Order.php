<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'subtotal', 'discount_type', 'discount_amount', 'total', 'original_total', 'status', 'customer_lat', 'customer_lng'])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'original_total' => 'decimal:2',
            'customer_lat' => 'decimal:7',
            'customer_lng' => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<OrderPlatformDiscountTier, $this>
     */
    public function platformDiscountTiers(): HasMany
    {
        return $this->hasMany(OrderPlatformDiscountTier::class);
    }

    public function refundAmount(): float
    {
        return round((float) $this->original_total - (float) $this->total, 2);
    }
}
