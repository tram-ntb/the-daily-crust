<?php

namespace App\Models;

/** Nhật ký nhập/xuất nguyên liệu (bảng `ingredient_stock_transactions`). */
class IngredientStockTransaction extends BakeryModel
{
    protected $table = 'ingredient_stock_transactions';

    protected $primaryKey = 'transaction_id';

    protected function casts(): array
    {
        return ['transaction_date' => 'datetime'];
    }
}
