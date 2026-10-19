<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kế hoạch sản xuất theo ngày (bảng `production_plans`). */
class ProductionPlan extends BakeryModel
{
    protected $table = 'production_plans';

    protected $primaryKey = 'plan_id';

    protected function casts(): array
    {
        return [
            'plan_date' => 'datetime',
            'approved_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductionBatch::class, 'plan_id', 'plan_id');
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by', 'user_id');
    }

    /** Quản lý đã chốt kế hoạch. */
    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /** Thợ làm bánh chính đã xác nhận tiếp nhận. */
    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }
}
