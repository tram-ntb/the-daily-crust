<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Phân bổ lô xuất kho cho một dòng chi tiết đơn (bảng `order_detail_lots`). */
class OrderDetailLot extends BakeryModel
{
    protected $table = 'order_detail_lots';

    protected $primaryKey = 'id';

    public function detail(): BelongsTo
    {
        return $this->belongsTo(OrderDetail::class, 'order_detail_id', 'detail_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class, 'lot_id', 'lot_id');
    }
}
