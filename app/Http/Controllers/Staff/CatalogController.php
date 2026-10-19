<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Danh mục kho: nguyên liệu, thành phẩm (tồn theo lô), nhà cung cấp. Cảnh báo vàng/đỏ theo mức tồn tối thiểu. */
class CatalogController extends Controller
{
    /** Thành phẩm không có "mức tồn tối thiểu" trong CSDL nên dùng ngưỡng cố định. */
    private const PRODUCT_LOW = 5;

    public function ingredients(Request $request): View
    {
        $q = trim((string) $request->input('q'));
        $level = $request->input('level');   // low | critical | ok

        $all = Ingredient::query()
            ->when($q !== '', fn (Builder $b) => $b->where('ingredient_name', 'like', "%{$q}%"))
            ->when($request->filled('category'), fn (Builder $b) => $b->where('category', $request->input('category')))
            ->orderBy('category')->orderBy('ingredient_name')
            ->get();

        $counts = [
            'critical' => $all->filter(fn (Ingredient $i) => $i->stockLevel() === Ingredient::LEVEL_CRITICAL)->count(),
            'low' => $all->filter(fn (Ingredient $i) => $i->stockLevel() === Ingredient::LEVEL_LOW)->count(),
            'total' => $all->count(),
        ];
        $items = $level ? $all->filter(fn (Ingredient $i) => $i->stockLevel() === $level)->values() : $all;

        return view('staff.catalog.index', [
            'tab' => 'ingredients',
            'items' => $items,
            'counts' => $counts,
            'categories' => Ingredient::query()->distinct()->orderBy('category')->pluck('category'),
            'filters' => ['q' => $q, 'level' => $level, 'category' => $request->input('category')],
        ]);
    }

    public function products(Request $request): View
    {
        $q = trim((string) $request->input('q'));

        $items = Product::query()
            ->withSum(['lots as stock_qty' => fn (Builder $b) => $b->available()], 'remaining_qty')
            ->withMin(['lots as nearest_expiry' => fn (Builder $b) => $b->available()], 'expiry_date')
            ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w->where('vi_name', 'like', "%{$q}%")->orWhere('item', 'like', "%{$q}%")))
            ->when($request->filled('category'), fn (Builder $b) => $b->where('category', $request->input('category')))
            ->orderBy('category')->orderBy('vi_name')
            ->get();

        $items->each(function (Product $p) {
            $stock = (int) ($p->stock_qty ?? 0);
            $p->stock_level = $stock <= 0 ? 'critical' : ($stock <= self::PRODUCT_LOW ? 'low' : 'ok');
        });

        return view('staff.catalog.index', [
            'tab' => 'products',
            'items' => $items,
            'counts' => [
                'critical' => $items->where('stock_level', 'critical')->count(),
                'low' => $items->where('stock_level', 'low')->count(),
                'total' => $items->count(),
            ],
            'categories' => Product::query()->distinct()->orderBy('category')->pluck('category'),
            'filters' => ['q' => $q, 'level' => null, 'category' => $request->input('category')],
            'productLow' => self::PRODUCT_LOW,
        ]);
    }

    public function suppliers(Request $request): View
    {
        $q = trim((string) $request->input('q'));

        $items = Supplier::query()
            ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w
                ->where('company_name', 'like', "%{$q}%")->orWhere('supplier_code', 'like', "%{$q}%")->orWhere('contact_name', 'like', "%{$q}%")))
            ->orderBy('company_name')
            ->get();

        return view('staff.catalog.index', [
            'tab' => 'suppliers',
            'items' => $items,
            'counts' => ['critical' => 0, 'low' => 0, 'total' => $items->count()],
            'categories' => collect(),
            'filters' => ['q' => $q, 'level' => null, 'category' => null],
        ]);
    }
}
