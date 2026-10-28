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

    /**
     * Sản phẩm SẢN XUẤT / PHA CHẾ tại quán (đưa vào kế hoạch sản xuất):
     *  - không thuộc nhóm hàng nhập (config forecast.commercial_categories);
     *  - nhóm do BOM quyết định (đồ uống): chỉ khi CÓ công thức BOM (nước suối đóng chai không có BOM nên bị loại).
     */
    public function scopeProducible(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        $commercial = (array) config('forecast.commercial_categories', []);
        $bomDecides = (array) config('forecast.bom_decides_categories', []);

        return $query->where(fn ($q) => $q->whereNull('category')->orWhere(
            fn ($w) => $w->whereNotIn('category', $commercial)
                ->where(fn ($x) => $x->whereNotIn('category', $bomDecides)->orWhereHas('boms'))
        ));
    }

    /** Sản phẩm PHA THEO ĐƠN (đồ uống pha tại quán): có dự báo và kế hoạch nguyên liệu nhưng không ghi nhận theo mẻ. */
    public function isMadeToOrder(): bool
    {
        return in_array($this->category, (array) config('forecast.made_to_order_categories', []), true);
    }

    /** Sản phẩm làm theo MẺ (bánh mì, bánh ngọt...): sản xuất tại quán và không thuộc nhóm pha theo đơn. Dùng cho ghi nhận / theo dõi mẻ. */
    public function scopeBatchable(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        $madeToOrder = (array) config('forecast.made_to_order_categories', []);

        return $query->producible()
            ->where(fn ($q) => $q->whereNull('category')->orWhereNotIn('category', $madeToOrder));
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
