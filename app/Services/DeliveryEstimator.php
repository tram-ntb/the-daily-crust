<?php

namespace App\Services;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/** Tính giờ dự kiến giao / sẵn sàng cho đơn online. Luôn cách lúc đặt tối thiểu `min_lead` phút (không bao giờ 0 phút). */
class DeliveryEstimator
{
    public function estimate(CarbonInterface $placedAt, string $deliveryMethod, int $units): Carbon
    {
        $c = (array) config('crust.delivery');

        $extra = min(
            (int) $c['prep_extra_cap'],
            max(0, $units - (int) $c['free_items']) * (int) $c['extra_per_item']
        );
        $minutes = (int) $c['prep_base'] + $extra + ($deliveryMethod === 'Delivery_2km' ? (int) $c['travel'] : 0);
        $minutes = max($minutes, (int) $c['min_lead']);

        // Cộng phút rồi làm tròn LÊN (không bao giờ làm tròn xuống), nên giờ hẹn luôn >= lúc đặt + số phút ở trên
        return $this->roundUp(Carbon::instance($placedAt)->copy()->addMinutes($minutes), (int) $c['round_to']);
    }

    /** Làm tròn LÊN mốc `$step` phút (13:01:10 với step 5 -> 13:05; 13:15:00 giữ nguyên) để giờ hẹn dễ đọc. */
    private function roundUp(Carbon $t, int $step): Carbon
    {
        $t = $t->copy();
        if ($t->second > 0 || $t->microsecond > 0) {
            $t->addMinute();                                   // có lẻ giây: lên phút kế tiếp
        }
        $t->setTime((int) $t->format('H'), (int) $t->format('i'), 0, 0);

        $step = max(1, $step);
        $over = $t->minute % $step;
        if ($over > 0) {
            $t->addMinutes($step - $over);
        }

        return $t;
    }

    /**
     * Giờ dự kiến TỚI NƠI khi đơn giao tận nơi bắt đầu đi giao (trạng thái "Ready"):
     * giờ xuất phát + thời gian di chuyển, làm tròn lên mốc `round_to` phút. Luôn lớn hơn giờ xuất phát.
     */
    public function estimateArrival(CarbonInterface $leftAt): Carbon
    {
        $c = (array) config('crust.delivery');

        return $this->roundUp(Carbon::instance($leftAt)->copy()->addMinutes(max(1, (int) $c['travel'])), (int) $c['round_to']);
    }

    /** Giờ dự kiến đã lưu của đơn (null với đơn cũ / đơn POS). */
    public static function eta(Order $order): ?Carbon
    {
        return $order->estimated_delivery_at ? Carbon::parse($order->estimated_delivery_at) : null;
    }
}
