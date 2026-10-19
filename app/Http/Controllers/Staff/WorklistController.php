<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Worklist: danh sách đơn hàng (mặc định là đơn ONLINE khách vừa đặt) để nhân viên chuẩn bị. */
class WorklistController extends Controller
{
    private const ACTIVE = [Order::STATUS_NEW, Order::STATUS_PREPARING, Order::STATUS_READY];

    public function index(Request $request): View
    {
        $channel = in_array($request->input('channel'), ['App', 'POS'], true) ? $request->input('channel') : 'App';
        $tab = $request->input('tab', 'active');   // active | New | Preparing | Ready | Completed

        $base = Order::query()->where('order_channel', $channel);

        $counts = [
            'active' => (clone $base)->whereIn('status', self::ACTIVE)->count(),
            Order::STATUS_NEW => (clone $base)->where('status', Order::STATUS_NEW)->count(),
            Order::STATUS_PREPARING => (clone $base)->where('status', Order::STATUS_PREPARING)->count(),
            Order::STATUS_READY => (clone $base)->where('status', Order::STATUS_READY)->count(),
            Order::STATUS_COMPLETED => (clone $base)->where('status', Order::STATUS_COMPLETED)->count(),
        ];

        $orders = (clone $base)
            ->with(['details.product', 'payment', 'delivery', 'creator'])
            ->when($tab === 'active', fn (Builder $q) => $q->whereIn('status', self::ACTIVE)->orderBy('order_datetime'))     // đơn cũ nhất làm trước
            ->when($tab !== 'active', fn (Builder $q) => $q->where('status', $tab)
                ->orderBy('order_datetime', $tab === Order::STATUS_COMPLETED ? 'desc' : 'asc'))
            ->paginate(20)->withQueryString();

        return view('staff.worklist.index', [
            'orders' => $orders,
            'counts' => $counts,
            'channel' => $channel,
            'tab' => $tab,
            'canUpdate' => $request->user()->role === 'staff',
        ]);
    }

    /** Chuyển đơn sang bước kế tiếp: New -> Preparing -> Ready -> Completed. */
    public function advance(Request $request, Order $order): RedirectResponse
    {
        try {
            DB::transaction(function () use ($order) {
                $locked = Order::lockForUpdate()->findOrFail($order->order_id);
                $next = Order::NEXT_STATUS[$locked->status] ?? null;

                if (! $next) {
                    throw ValidationException::withMessages(['status' => "Đơn #{$locked->order_id} đã hoàn tất, không thể chuyển tiếp."]);
                }

                $changes = ['status' => $next];
                if ($next === Order::STATUS_READY) {
                    $changes['prepared_at'] = now();
                }
                if ($next === Order::STATUS_COMPLETED) {
                    $changes['completed_at'] = now();
                }
                $locked->update($changes);

                // Ghi vết tượng trưng khi giao/giao xong
                if ($next === Order::STATUS_COMPLETED) {
                    $locked->payment()?->update(['status' => 'Completed', 'paid_at' => now()]);
                    $locked->delivery()?->update(['delivery_status' => 'Delivered', 'delivered_at' => now()]);
                } elseif ($next === Order::STATUS_READY) {
                    $locked->delivery()?->update(['delivery_status' => 'Ready', 'picked_up_at' => now()]);
                }
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', "Đã cập nhật trạng thái đơn #{$order->order_id}.");
    }
}
