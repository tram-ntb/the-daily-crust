<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderDetailLot;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\ProductStockMovement;
use App\Models\ProductStockTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tạo đơn hàng dùng chung cho POS (thu ngân) và đặt hàng online (khách):
 *  - giá luôn lấy từ bảng products, KHÔNG tin giá do trình duyệt gửi lên;
 *  - mỗi sản phẩm = 1 dòng order_details (quantity = 1), đúng cách dữ liệu lịch sử được lưu;
 *  - mỗi dòng gắn với 1 lô trong order_detail_lots theo FIFO/FEFO (lô sắp hết hạn xuất trước);
 *  - trừ product_lots.remaining_qty và ghi sổ kho; tất cả trong MỘT transaction có khóa dòng.
 */
class OrderService
{
    public const PAYMENT_METHODS = ['Cash', 'QR_Code', 'Momo'];

    public const DELIVERY_METHODS = ['In_Store', 'Delivery_2km'];

    /**
     * @param  array<int, array{product_id:int|string, qty:int|string}>  $items
     * @param  array{channel:string, payment_method:string, delivery_method?:string, delivery_address?:?string, note?:?string, cash_received?:int|float|null}  $opts
     */
    public function place(array $items, array $opts, User $user): Order
    {
        $items = $this->mergeItems($items);

        return DB::transaction(function () use ($items, $opts, $user) {
            $now = now();
            $isPos = $opts['channel'] === Order::CHANNEL_POS;

            $products = Product::with('boms.ingredient')
                ->whereIn('product_id', array_keys($items))->get()->keyBy('product_id');

            // 1) Kiểm tra sản phẩm + gom lô bán được (khóa dòng để hai thu ngân không bán trùng một lô)
            $total = 0;
            $plan = [];       // product_id => [price, lots(Collection)]
            $errors = [];
            foreach ($items as $pid => $qty) {
                $product = $products[$pid] ?? null;
                if (! $product || ($product->status !== null && $product->status !== 'Active')) {
                    $errors[] = "Sản phẩm #{$pid} không còn được bán.";

                    continue;
                }

                $lots = ProductLot::sellable()->where('product_id', $pid)
                    ->orderBy('expiry_date')->orderBy('lot_id')->lockForUpdate()->get();
                $stock = (int) $lots->sum('remaining_qty');

                if ($stock < $qty) {
                    $errors[] = "{$product->vi_name}: chỉ còn {$stock} sản phẩm (yêu cầu {$qty}).";

                    continue;
                }

                $plan[$pid] = ['price' => (int) $product->price, 'lots' => $lots];
                $total += (int) $product->price * $qty;
            }
            if ($errors) {
                throw ValidationException::withMessages(['cart' => $errors]);
            }

            // 2) Đơn hàng
            $cashReceived = $isPos && $opts['payment_method'] === 'Cash' ? ($opts['cash_received'] ?? null) : null;
            if ($cashReceived !== null && $cashReceived < $total) {
                throw ValidationException::withMessages(['cash_received' => 'Tiền khách đưa chưa đủ để thanh toán.']);
            }

            $delivery = $opts['delivery_method'] ?? 'In_Store';
            $order = Order::create([
                'order_datetime' => $now,
                'period_day' => $now->hour >= 6 && $now->hour < 12 ? 'Morning' : ($now->hour < 18 && $now->hour >= 12 ? 'Afternoon' : 'Evening'),
                'weekday_weekend' => $now->isWeekend() ? 'Weekend' : 'Weekday',
                'total_amount' => $total,
                'order_channel' => $opts['channel'],
                'payment_method' => $opts['payment_method'],
                'delivery_method' => $delivery,
                'delivery_address' => $delivery === 'Delivery_2km' ? ($opts['delivery_address'] ?? null) : null,
                'prepared_at' => $isPos ? $now : null,
                'completed_at' => $isPos ? $now : null,
                'created_by' => $user->user_id,
                'note' => $opts['note'] ?? null,
                'status' => $isPos ? Order::STATUS_COMPLETED : Order::STATUS_NEW,
            ]);

            // 3) Dòng chi tiết + phân bổ lô (FEFO), gom số lượng đã xuất theo từng lô
            $takenByLot = [];
            foreach ($plan as $pid => $info) {
                $lots = $info['lots'];
                $cursor = 0;
                $left = $lots[$cursor]->remaining_qty;

                for ($i = 0; $i < $items[$pid]; $i++) {
                    while ($left <= 0) {            // sang lô kế tiếp khi lô hiện tại đã hết
                        $cursor++;
                        $left = $lots[$cursor]->remaining_qty;
                    }
                    $lot = $lots[$cursor];

                    $detail = OrderDetail::create([
                        'order_id' => $order->order_id,
                        'product_id' => $pid,
                        'price' => $info['price'],
                        'quantity' => 1,
                    ]);
                    OrderDetailLot::create([
                        'order_detail_id' => $detail->detail_id,
                        'lot_id' => $lot->lot_id,
                        'quantity' => 1,
                    ]);

                    $takenByLot[$lot->lot_id] = ($takenByLot[$lot->lot_id] ?? 0) + 1;
                    $left--;
                    $lot->remaining_qty--;          // cập nhật bản sao trong bộ nhớ để vòng sau tính đúng
                }
            }

            // 4) Trừ tồn các lô + sổ kho
            foreach ($takenByLot as $lotId => $qty) {
                $lot = ProductLot::findOrFail($lotId);
                $lot->decrement('remaining_qty', $qty);
                $unitCost = $products[$lot->product_id]->unitCost();

                ProductStockMovement::create([
                    'product_id' => $lot->product_id, 'lot_id' => $lotId, 'movement_type' => 'Sale_Out',
                    'quantity' => $qty, 'unit_cost' => $unitCost, 'total_cost' => $unitCost * $qty,
                    'reference_type' => 'Order', 'reference_id' => $order->order_id,
                    'movement_date' => $now, 'user_id' => $user->user_id, 'note' => 'Bán hàng đơn #'.$order->order_id,
                ]);
                ProductStockTransaction::create([
                    'product_id' => $lot->product_id, 'lot_id' => $lotId, 'transaction_type' => 'Sale',
                    'quantity' => $qty, 'reference_type' => 'Order', 'reference_id' => $order->order_id,
                    'transaction_date' => $now,
                ]);
            }

            // 5) Thanh toán (tượng trưng): POS thu ngay; đơn online ghi nhận "Pending" tới khi giao xong
            Payment::create([
                'order_id' => $order->order_id,
                'method' => $opts['payment_method'],
                'amount' => $total,
                'status' => $isPos ? 'Completed' : 'Pending',
                'transaction_ref' => null,
                'cash_received' => $cashReceived,
                'change_amount' => $cashReceived !== null ? $cashReceived - $total : null,
                'paid_at' => $isPos ? $now : null,
            ]);

            // 6) Giao hàng (tượng trưng) cho đơn online giao tận nơi
            if (! $isPos && $delivery === 'Delivery_2km') {
                DeliveryOrder::create([
                    'order_id' => $order->order_id,
                    'delivery_staff_id' => null,
                    'delivery_status' => 'Pending',
                    'distance_km' => null,
                    'delivery_fee' => 0,
                    'delivery_address' => $opts['delivery_address'] ?? null,
                    'delivery_phone' => is_numeric($user->phone) ? (int) $user->phone : null,
                    'cod_amount' => $opts['payment_method'] === 'Cash' ? $total : 0,
                    'note' => $opts['note'] ?? null,
                ]);
            }

            return $order->load('details.product');
        });
    }

    /** Gộp các dòng trùng sản phẩm và kiểm tra số lượng hợp lệ. @return array<int,int> product_id => qty */
    private function mergeItems(array $items): array
    {
        $merged = [];
        foreach ($items as $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            $qty = (int) ($row['qty'] ?? 0);
            if ($pid <= 0 || $qty <= 0) {
                continue;
            }
            $merged[$pid] = ($merged[$pid] ?? 0) + $qty;
        }

        if (! $merged) {
            throw ValidationException::withMessages(['cart' => 'Giỏ hàng đang trống.']);
        }
        foreach ($merged as $pid => $qty) {
            if ($qty > 99) {
                throw ValidationException::withMessages(['cart' => 'Mỗi sản phẩm tối đa 99 trong một đơn.']);
            }
        }

        return $merged;
    }
}
