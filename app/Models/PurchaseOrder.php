<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Đơn mua nguyên liệu gửi nhà cung cấp (bảng `purchase_orders`). Trạng thái: Pending (chờ nhận hàng) | Received | Cancelled. */
class PurchaseOrder extends BakeryModel
{
    public const PENDING = 'Pending';
    public const RECEIVED = 'Received';
    public const CANCELLED = 'Cancelled';

    protected $table = 'purchase_orders';

    protected $primaryKey = 'po_id';

    protected function casts(): array
    {
        return ['order_date' => 'datetime', 'expected_delivery_date' => 'datetime'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(PurchaseOrderDetail::class, 'po_id', 'po_id');
    }
}
