<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Khách hàng đặt hàng online từ trang chủ; đơn đi thẳng vào Worklist của nhân viên. */
class OnlineOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly CartService $cart,
    ) {
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'delivery_method' => ['required', Rule::in(OrderService::DELIVERY_METHODS)],
            'delivery_address' => ['required_if:delivery_method,Delivery_2km', 'nullable', 'string', 'max:255'],
            'payment_method' => ['required', Rule::in(OrderService::PAYMENT_METHODS)],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'delivery_address.required_if' => 'Vui lòng nhập địa chỉ giao hàng.',
        ]);

        // Giỏ hàng nằm trong CSDL: dùng đúng giỏ của tài khoản này, không tin danh sách do trình duyệt gửi lên.
        $items = $this->cart->itemsForOrder($request->user());
        if ($items === []) {
            return back()->withErrors(['cart' => 'Giỏ hàng đang trống.']);
        }

        try {
            $order = $this->orders->place($items, [
                'channel' => Order::CHANNEL_APP,
                'payment_method' => $data['payment_method'],
                'delivery_method' => $data['delivery_method'],
                'delivery_address' => $data['delivery_address'] ?? null,
                'note' => $data['note'] ?? null,
            ], $request->user());
        } catch (ValidationException $e) {
            // Lỗi (ví dụ hết hàng giữa chừng): giữ nguyên giỏ để khách chỉnh lại
            return back()->withErrors($e->errors())->withInput();
        }

        $this->cart->clear($request->user());   // đặt thành công mới xóa giỏ

        return redirect()->route('orders.mine')
            ->with('success', "Đặt hàng thành công! Mã đơn #{$order->order_id}. Tiệm đang chuẩn bị cho bạn.")
            ->with('clear_cart', true);   // vẫn xóa khóa LocalStorage cũ (nếu còn)
    }

    /** "Đơn hàng của tôi": khách theo dõi trạng thái đơn đã đặt. */
    public function mine(Request $request): View
    {
        $orders = Order::query()
            ->with('details.product')
            ->where('created_by', $request->user()->user_id)
            ->where('order_channel', Order::CHANNEL_APP)
            ->orderByDesc('order_datetime')
            ->paginate(10);

        return view('customer.orders.index', ['orders' => $orders]);
    }
}
