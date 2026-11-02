<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductLot;

/**
 * NGUỒN DUY NHẤT cho "còn bao nhiêu để bán" (trang chủ, chi tiết, giỏ hàng, POS, đặt đơn đều gọi vào đây):
 *  - Hàng làm theo mẻ (bánh mì, bánh ngọt, nước suối đóng chai...): Σ remaining_qty của các lô BÁN ĐƯỢC (còn hàng và chưa hết hạn).
 *  - Đồ uống PHA THEO ĐƠN (nhóm made_to_order và có BOM): không có lô thành phẩm; số ly bán được = số ly mà tồn nguyên liệu
 *    còn đủ pha theo BOM (nguyên liệu nào cạn trước thì quyết định).
 */
class StockService
{
    /** Số lượng tối đa hiển thị cho đồ uống pha theo đơn (tránh hiện số khổng lồ). */
    public const MADE_TO_ORDER_CAP = 99;

    /** Sản phẩm này bán theo nguyên liệu (pha theo đơn) thay vì theo lô? */
    public function usesIngredients(Product $product): bool
    {
        $product->loadMissing('boms');

        return $product->isMadeToOrder() && $product->boms->isNotEmpty();
    }

    /**
     * @param  array<int,int|string>  $productIds
     * @return array<int,int> product_id => số lượng bán được
     */
    public function availability(array $productIds): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if ($productIds === []) {
            return [];
        }

        $lotStock = ProductLot::sellable()
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'remaining_qty'])
            ->groupBy('product_id')
            ->map(fn ($lots) => (int) $lots->sum('remaining_qty'));

        $out = [];
        foreach (Product::with('boms.ingredient')->whereIn('product_id', $productIds)->get() as $p) {
            $out[$p->product_id] = $this->usesIngredients($p)
                ? $this->capacityFromIngredients($p)
                : (int) ($lotStock[$p->product_id] ?? 0);
        }

        return $out;
    }

    /** Số ly pha được từ tồn nguyên liệu hiện tại (ngưỡng theo nguyên liệu cạn nhất). */
    public function capacityFromIngredients(Product $product): int
    {
        $product->loadMissing('boms.ingredient');
        $cap = self::MADE_TO_ORDER_CAP;
        foreach ($product->boms as $bom) {
            $per = (float) $bom->quantity_required;
            if ($per <= 0) {
                continue;
            }
            $have = (float) ($bom->ingredient->stock_quantity ?? 0);
            $cap = min($cap, (int) floor($have / $per));
        }

        return max(0, $cap);
    }
}
