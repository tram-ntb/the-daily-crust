<?php

namespace App\Services;

use App\Models\ProductLot;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Nhận diện thành phẩm cận date / quá hạn và lập phiếu hủy. Chỉ dùng Eloquent. */
class ExpiryService
{
    public const STATE_EXPIRED = 'expired';

    public const STATE_NEAR = 'near';

    public const STATE_OK = 'ok';

    /** Cận date = thời hạn còn lại không quá 30% tổng thời hạn sử dụng của lô. */
    public const NEAR_RATIO = 0.30;

    public function __construct(private readonly InventoryService $inventory) {}

    /** Phân loại trạng thái hạn dùng của một lô. */
    public function stateOf(ProductLot $lot): string
    {
        $left = $lot->hoursLeft();
        if ($left === null) {
            return self::STATE_OK;
        }
        if ($left <= 0) {
            return self::STATE_EXPIRED;
        }
        $shelf = $lot->shelfLifeHours();

        return ($shelf !== null && $left <= $shelf * self::NEAR_RATIO) ? self::STATE_NEAR : self::STATE_OK;
    }

    /**
     * Toàn bộ lô còn hàng kèm trạng thái, giá vốn và giá trị; lô gần hết hạn nhất lên đầu.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function lots(): Collection
    {
        return ProductLot::available()
            ->with('product.boms.ingredient')
            ->orderBy('expiry_date')
            ->get()
            ->map(function (ProductLot $lot) {
                $left = $lot->hoursLeft();
                $shelf = $lot->shelfLifeHours();
                $unitCost = $lot->product ? $lot->product->unitCost() : 0;

                return [
                    'lot' => $lot,
                    'state' => $this->stateOf($lot),
                    'hours_left' => $left,
                    'percent_left' => ($left !== null && $shelf) ? (int) max(0, min(100, round($left / $shelf * 100))) : null,
                    'unit_cost' => $unitCost,
                    'value' => $unitCost * (int) $lot->remaining_qty,
                ];
            })
            ->values();
    }

    /** Lập phiếu hủy một phần/toàn bộ lô (dùng lại nghiệp vụ phiếu hủy của kho). */
    public function destroyLot(int $lotId, int $qty, string $reason, User $user)
    {
        return $this->inventory->recordWaste([
            'item_type' => 'Product',
            'lot_id' => $lotId,
            'quantity' => $qty,
            'reason_type' => $reason,
        ], $user);
    }

    /**
     * Hủy toàn bộ phần còn lại của MỌI lô đã quá hạn trong một transaction.
     *
     * @return array{tickets:int, quantity:int, cost:int}
     */
    public function destroyAllExpired(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $lots = ProductLot::available()->whereNotNull('expiry_date')->where('expiry_date', '<=', now())->lockForUpdate()->get();
            if ($lots->isEmpty()) {
                throw ValidationException::withMessages(['expiry' => 'Hiện không có lô nào quá hạn để hủy.']);
            }

            $total = ['tickets' => 0, 'quantity' => 0, 'cost' => 0];
            foreach ($lots as $lot) {
                $ticket = $this->destroyLot($lot->lot_id, (int) $lot->remaining_qty, 'Hết hạn sử dụng', $user);
                $total['tickets']++;
                $total['quantity'] += (int) $ticket->quantity;
                $total['cost'] += (int) $ticket->total_cost;
            }

            return $total;
        });
    }
}
