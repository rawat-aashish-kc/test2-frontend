<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'price', 'is_active'])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Inventory, $this>
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * @return HasMany<ProductDiscount, $this>
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(ProductDiscount::class);
    }

    public function activeDiscounts(): HasMany
    {
        return $this->discounts()->where('is_active', true)->orderBy('min_quantity');
    }
}
