<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Điều phối giao hàng (bảng `delivery_orders`) - chỉ lưu vết tượng trưng cho đơn giao tận nơi. */
class DeliveryOrder extends BakeryModel
{
    protected $table = 'delivery_orders';

    protected $primaryKey = 'delivery_id';

    protected function casts(): array
    {
        return ['picked_up_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }
}
