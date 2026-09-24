<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['min_order_amount', 'discount_percent', 'is_active'])]
class PlatformDiscount extends Model
{
    protected function casts(): array
    {
        return [
            'min_order_amount' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
