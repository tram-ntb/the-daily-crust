<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Manager XEM thông tin tài khoản khách hàng (chỉ đọc, chặn ở route). */
class CustomerAccountController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->input('q', ''));

        $customers = User::query()
            ->where('role', 'customer')
            ->withCount('onlineOrders')
            ->withSum('onlineOrders as orders_total', 'total_amount')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('full_name', 'like', "%{$q}%")->orWhere('username', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")->orWhere('phone', 'like', '%'.ltrim($q, '0').'%')))
            ->orderBy('full_name')
            ->paginate(20)->withQueryString();

        return view('admin.users.customers-index', ['customers' => $customers, 'q' => $q]);
    }

    public function show(User $user): View
    {
        abort_unless($user->role === 'customer', 404);

        $orders = $user->onlineOrders()->with('details.product')->orderByDesc('order_datetime')->limit(10)->get();

        return view('admin.users.customers-show', [
            'customer' => $user,
            'orders' => $orders,
            'orderCount' => $user->onlineOrders()->count(),
            'orderTotal' => (int) $user->onlineOrders()->sum('total_amount'),
        ]);
    }
}
