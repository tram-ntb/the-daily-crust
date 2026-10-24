<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Product;
use App\Services\BomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Công thức định lượng nguyên liệu (BOM): Staff và Manager xem, CHỈ Manager sửa (chặn ở route). */
class BomController extends Controller
{
    public function __construct(private readonly BomService $bom) {}

    /** Danh sách sản phẩm kèm số nguyên liệu, giá vốn theo BOM và biên lợi nhuận. */
    public function index(Request $request): View
    {
        $q = trim((string) $request->input('q'));
        $category = (string) $request->input('category');
        $filter = (string) $request->input('filter');   // missing = chưa có BOM

        $products = Product::active()
            ->with('boms.ingredient')
            ->withCount('boms')
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('vi_name', 'like', "%{$q}%")->orWhere('item', 'like', "%{$q}%");
            }))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($filter === 'missing', fn ($query) => $query->doesntHave('boms'))
            ->orderBy('category')->orderBy('vi_name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.bom.index', [
            'products' => $products,
            'categories' => Product::active()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'filters' => ['q' => $q, 'category' => $category, 'filter' => $filter],
            'totalProducts' => Product::active()->count(),
            'withBom' => Product::active()->has('boms')->count(),
            'canEdit' => $request->user()->role === 'manager',
        ]);
    }

    /** Chi tiết công thức của một sản phẩm: các nguyên liệu thô cần dùng cho 1 sản phẩm. */
    public function show(Request $request, Product $product): View
    {
        $product->load('boms.ingredient');
        $cost = $product->unitCost();
        $canEdit = $request->user()->role === 'manager';

        return view('admin.bom.show', [
            'product' => $product,
            'lines' => $product->boms->sortBy(fn ($b) => $b->ingredient->ingredient_name ?? '')->map(fn ($b) => [
                'ingredient_id' => (int) $b->ingredient_id,
                'name' => $b->ingredient->ingredient_name ?? ('NL #'.$b->ingredient_id),
                'unit' => $b->ingredient->unit ?? $b->unit,
                'quantity' => (float) $b->quantity_required,
                'stock' => (int) ($b->ingredient->stock_quantity ?? 0),
                'cost' => (int) ($b->ingredient->cost_price_vn ?? 0),
            ])->values(),
            'ingredients' => $canEdit
                ? Ingredient::orderBy('ingredient_name')->get(['ingredient_id', 'ingredient_name', 'unit', 'stock_quantity', 'cost_price_vn'])
                    ->map(fn ($i) => [
                        'id' => (int) $i->ingredient_id, 'name' => $i->ingredient_name, 'unit' => $i->unit,
                        'stock' => (int) $i->stock_quantity, 'cost' => (int) $i->cost_price_vn,
                    ])->values()
                : collect(),
            'unitCost' => $cost,
            'margin' => $product->price > 0 ? round(($product->price - $cost) / $product->price * 100, 1) : null,
            'capacity' => $this->bom->capacity($product),
            'canEdit' => $canEdit,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:60'],
            'lines.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,ingredient_id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
        ], [
            'lines.required' => 'Công thức phải có ít nhất một nguyên liệu.',
            'lines.min' => 'Công thức phải có ít nhất một nguyên liệu.',
            'lines.*.ingredient_id.required' => 'Vui lòng chọn nguyên liệu cho mọi dòng.',
            'lines.*.ingredient_id.exists' => 'Có nguyên liệu không tồn tại trong kho.',
            'lines.*.quantity.required' => 'Vui lòng nhập định lượng cho mọi dòng.',
            'lines.*.quantity.min' => 'Định lượng phải lớn hơn 0.',
        ]);

        try {
            $stats = $this->bom->sync($product, $data['lines']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.bom.show', $product)
            ->with('success', "Đã lưu công thức: thêm {$stats['added']}, sửa {$stats['updated']}, xóa {$stats['removed']} dòng.");
    }
}
