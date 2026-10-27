<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductLot;
use Illuminate\View\View;

/**
 * Trang chi tiết sản phẩm cho MỌI người (khách vãng lai, khách hàng, nhân viên, quản lý):
 * liệt kê các nguyên liệu thô có trong sản phẩm, KHÔNG hiển thị định lượng.
 *
 * Bảo mật dữ liệu: cột quantity_required không bao giờ được nạp vào bộ nhớ ở đây (chỉ chọn tên nguyên liệu),
 * nên dù sau này ai sửa view cũng không thể vô tình in ra định lượng.
 */
class ProductDetailController extends Controller
{
    public function show(Product $product): View
    {
        // Chỉ lấy mã + tên nguyên liệu. Sắp xếp theo chữ cái để không lộ thứ tự nhiều/ít của công thức.
        $ingredients = $product->boms()
            ->with('ingredient:ingredient_id,ingredient_name')
            ->get(['bom_id', 'product_id', 'ingredient_id', 'ingredient_name'])
            ->map(fn ($bom) => trim((string) ($bom->ingredient->ingredient_name ?? $bom->ingredient_name)))
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $stock = (int) ProductLot::sellable()->where('product_id', $product->product_id)->sum('remaining_qty');

        $related = Product::query()
            ->where('product_id', '!=', $product->product_id)
            ->when($product->category, fn ($q) => $q->where('category', $product->category))
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'Active'))
            ->orderBy('vi_name')
            ->limit(4)
            ->get();

        return view('products.show', [
            'product' => $product,
            'ingredients' => $ingredients,
            'stock' => $stock,
            'related' => $related,
        ]);
    }
}
