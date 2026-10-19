<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Màn hình POS cho thu ngân: lưới sản phẩm + giỏ hàng (Alpine.js) + thanh toán tại quầy. */
class PosController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    public function index(): View
    {
        $products = Product::query()
            ->where(fn (Builder $q) => $q->whereNull('status')->orWhere('status', 'Active'))
            ->withSum(['lots as stock_qty' => fn (Builder $q) => $q->sellable()], 'remaining_qty')
            ->orderBy('category')->orderBy('vi_name')
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->product_id,
                'name' => $p->vi_name,
                'sub' => $p->item,
                'price' => (int) $p->price,
                'category' => $p->category ?? 'Khác',
                'stock' => (int) ($p->stock_qty ?? 0),
                // Chỉ trả URL ảnh khi file thật sự tồn tại, nếu không giao diện hiện biểu tượng thay thế
                'image' => $p->image_url && file_exists(public_path($p->image_url)) ? asset($p->image_url) : null,
            ])->values();

        return view('staff.pos.index', [
            'products' => $products,
            'categories' => $products->groupBy('category')->map->count(),
            'cashier' => auth()->user()->full_name ?? auth()->user()->username,
        ]);
    }

    /** Nhận giỏ hàng (JSON) từ Alpine.js, tạo đơn POS và trả kết quả để hiện hóa đơn. */
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:60'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,product_id'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:99'],
            'payment_method' => ['required', Rule::in(OrderService::PAYMENT_METHODS)],
            'cash_received' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ], [
            'items.required' => 'Giỏ hàng đang trống.',
            'items.min' => 'Giỏ hàng đang trống.',
        ]);

        $order = $this->orders->place($data['items'], [
            'channel' => 'POS',
            'payment_method' => $data['payment_method'],
            'delivery_method' => 'In_Store',
            'cash_received' => $data['cash_received'] ?? null,
        ], $request->user());

        $payment = $order->payment()->first();

        return response()->json([
            'ok' => true,
            'order_id' => $order->order_id,
            'total' => $order->total_amount,
            'payment_method' => $order->payment_method,
            'cash_received' => $payment?->cash_received !== null ? (int) $payment->cash_received : null,
            'change' => $payment?->change_amount !== null ? (int) $payment->change_amount : null,
            'time' => $order->order_datetime->format('H:i d/m/Y'),
            'lines' => $order->lineSummary(),
        ]);
    }
}
