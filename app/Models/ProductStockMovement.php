<?php

namespace App\Models;

/** Sổ nhập/xuất thành phẩm có giá vốn (bảng `product_stock_movements`). */
class ProductStockMovement extends BakeryModel
{
    protected $table = 'product_stock_movements';

    protected $primaryKey = 'movement_id';

    protected function casts(): array
    {
        return ['movement_date' => 'datetime'];
    }
}
