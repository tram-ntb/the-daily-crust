<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lô thành phẩm trong kho (bảng `product_lots`). Tồn kho thành phẩm = Σ remaining_qty của các lô còn hàng. */
class ProductLot extends BakeryModel
{
    public const STATUS_ACTIVE = 'Active';

    protected $table = 'product_lots';

    protected $primaryKey = 'lot_id';

    protected function casts(): array
    {
        return [
            'production_date' => 'datetime',
            'expiry_date' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    /** Lô còn hàng (chưa xét hạn dùng). */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)->where('remaining_qty', '>', 0);
    }

    /** Lô BÁN ĐƯỢC: còn hàng và chưa hết hạn dùng. Phân hệ bán hàng chỉ xuất từ các lô này. */
    public function scopeSellable(Builder $query): Builder
    {
        return $query->available()->where(function (Builder $q) {
            $q->whereNull('expiry_date')->orWhere('expiry_date', '>', now());
        });
    }
}
