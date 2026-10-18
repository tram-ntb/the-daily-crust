<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Trang chủ cửa hàng (mua hàng) — chỉ dành cho khách vãng lai và customer.
 * Staff/manager được chuyển sang dashboard quản trị riêng.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if (in_array($request->user()?->role, ['manager', 'staff'], true)) {
            return redirect()->route('admin.dashboard');
        }

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

        return view('dashboard', compact('products'));
    }
}
