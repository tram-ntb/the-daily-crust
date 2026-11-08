<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Phiếu đề xuất mua nguyên liệu (bảng `purchase_requisitions`). Trạng thái: Pending | Approved. */
class PurchaseRequisition extends BakeryModel
{
    public const PENDING = 'Pending';
    public const APPROVED = 'Approved';

    protected $table = 'purchase_requisitions';

    protected $primaryKey = 'requisition_id';

    protected function casts(): array
    {
        // approved_at chỉ cast datetime SAU KHI chạy migration đổi cột từ DECIMAL sang DATETIME
        return [
            'requisition_date' => 'datetime', 'expected_delivery_date' => 'datetime',
            'approved_at' => 'datetime', 'created_at' => 'datetime',
        ];
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
        return $this->hasMany(PurchaseRequisitionDetail::class, 'requisition_id', 'requisition_id');
    }
}
