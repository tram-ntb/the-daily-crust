<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use App\Models\ProductLot;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = DB::table('products');
        $search = $request->string('search')->trim()->toString();

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('vi_name', 'like', '%'.$search.'%')
                    ->orWhere('item', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('price_from')) {
            $query->where('price', '>=', (float) $request->input('price_from') * 1000);
        }

        if ($request->filled('price_to')) {
            $query->where('price', '<=', (float) $request->input('price_to') * 1000);
        }

        if ($request->input('sort') === 'price_asc') {
            $query->orderBy('price');
        } elseif ($request->input('sort') === 'price_desc') {
            $query->orderByDesc('price');
        } elseif ($request->input('sort') === 'best_seller') {
            $query->orderByDesc('product_id');
        } else {
            $query->orderBy('item')->orderBy('product_id');
        }

        $products = $query->paginate(12)->withQueryString();

        // Tồn kho BÁN ĐƯỢC (lô còn hàng, chưa hết hạn) của các sản phẩm trong trang này,
        // để thẻ sản phẩm hiện "Hết hàng" và chặn nút thêm vào giỏ ngay từ đầu.
        $stock = ProductLot::sellable()
            ->whereIn('product_id', $products->pluck('product_id')->all())
            ->get(['product_id', 'remaining_qty'])
            ->groupBy('product_id')
            ->map(fn ($lots) => (int) $lots->sum('remaining_qty'));

        $products->getCollection()->each(function ($p) use ($stock) {
            $p->stock = (int) ($stock[$p->product_id] ?? 0);
        });

        return view('dashboard', compact('products'));
    }
}
