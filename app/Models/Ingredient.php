<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Nguyên liệu thô (bảng `ingredients`) — tương ứng "Material" trong mô tả. */
class Ingredient extends BakeryModel
{
    public const LEVEL_CRITICAL = 'critical';
    public const LEVEL_LOW = 'low';
    public const LEVEL_OK = 'ok';

    protected $table = 'ingredients';

    protected $primaryKey = 'ingredient_id';

    public function boms(): HasMany
    {
        return $this->hasMany(BillOfMaterial::class, 'ingredient_id', 'ingredient_id');
    }

    /** Tồn kho nhỏ hơn hoặc bằng mức tối thiểu. */
    public function scopeLow(Builder $query): Builder
    {
        return $query->whereColumn('stock_quantity', '<=', 'min_stock_level');
    }

    /** critical (đỏ): hết hoặc ≤ 50% mức tối thiểu | low (vàng): ≤ mức tối thiểu | ok. */
    public function stockLevel(): string
    {
        $stock = (int) $this->stock_quantity;
        $min = (int) $this->min_stock_level;

        if ($min <= 0) {
            return $stock <= 0 ? self::LEVEL_CRITICAL : self::LEVEL_OK;
        }
        if ($stock <= 0 || $stock <= $min * 0.5) {
            return self::LEVEL_CRITICAL;
        }

        return $stock <= $min ? self::LEVEL_LOW : self::LEVEL_OK;
    }
}
