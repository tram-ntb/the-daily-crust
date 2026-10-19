<?php

namespace App\Models;

/** Nhật ký giao dịch kho thành phẩm (bảng `product_stock_transactions`). */
class ProductStockTransaction extends BakeryModel
{
    protected $table = 'product_stock_transactions';

    protected $primaryKey = 'transaction_id';

    protected function casts(): array
    {
        return ['transaction_date' => 'datetime'];
    }
}
