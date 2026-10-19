<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Thanh toán (bảng `payments`) - chỉ lưu vết tượng trưng: phương thức, số tiền, trạng thái. */
class Payment extends BakeryModel
{
    protected $table = 'payments';

    protected $primaryKey = 'payment_id';

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }
}
