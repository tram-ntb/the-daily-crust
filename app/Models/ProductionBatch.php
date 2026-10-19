<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Nhật ký mẻ nướng (bảng `production_batches`) — tương ứng "ProductionLog" trong mô tả. */
class ProductionBatch extends BakeryModel
{
    protected $table = 'production_batches';

    protected $primaryKey = 'batch_id';

    protected function casts(): array
    {
        return ['batch_date' => 'datetime'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class, 'plan_id', 'plan_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function lot(): HasOne
    {
        return $this->hasOne(ProductLot::class, 'batch_id', 'batch_id');
    }

    /** Tỷ lệ đạt chuẩn (%). */
    public function passRate(): int
    {
        return $this->produced_qty > 0 ? (int) round($this->passed_qty / $this->produced_qty * 100) : 0;
    }
}
