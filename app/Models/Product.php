<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/** Sản phẩm / thành phẩm (bảng `products`). Tồn kho thành phẩm nằm ở các lô `product_lots`. */
class Product extends BakeryModel
{
    protected $table = 'products';

    protected $primaryKey = 'product_id';

    public function lots(): HasMany
    {
        return $this->hasMany(ProductLot::class, 'product_id', 'product_id');
    }

    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class, 'product_id', 'product_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(ProductionPlan::class, 'product_id', 'product_id');
    }

    public function forecasts(): HasMany
    {
        return $this->hasMany(ProductionForecast::class, 'product_id', 'product_id');
    }

    /** Sản phẩm SẢN XUẤT tại bếp (không thuộc nhóm hàng nhập về cấu hình ở config/forecast.php). */
    public function scopeProducible(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        $commercial = (array) config('forecast.commercial_categories', []);

        return $query->where(fn ($q) => $q->whereNull('category')->orWhereNotIn('category', $commercial));
    }

    /** Đang kinh doanh (status NULL hoặc 'Active'). */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('status')->orWhere('status', 'Active');
        });
    }

    public function boms(): HasMany
    {
        return $this->hasMany(BillOfMaterial::class, 'product_id', 'product_id');
    }

    /** Giá vốn nguyên liệu của 1 sản phẩm = Σ(định mức BOM × giá vốn nguyên liệu). */
    public function unitCost(): int
    {
        $this->loadMissing('boms.ingredient');

        return (int) round($this->boms->sum(
            fn (BillOfMaterial $bom) => $bom->quantity_required * ($bom->ingredient->cost_price_vn ?? 0)
        ));
    }
}
