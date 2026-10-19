<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Chi tiết đơn: mỗi dòng là 1 sản phẩm (đơn giá `price`), giống cách dữ liệu lịch sử được lưu. */
class OrderDetail extends BakeryModel
{
    protected $table = 'order_details';

    protected $primaryKey = 'detail_id';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    /** Các lô thành phẩm đã xuất cho dòng này (truy vết nguồn hàng). */
    public function lots(): HasMany
    {
        return $this->hasMany(OrderDetailLot::class, 'order_detail_id', 'detail_id');
    }
}
