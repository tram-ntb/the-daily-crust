<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\IngredientStockTransaction;
use App\Models\InventoryWaste;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\ProductStockMovement;
use App\Models\ProductStockTransaction;
use App\Models\ShortageExplanation;
use App\Models\StocktakeDetail;
use App\Models\StocktakeSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Nghiệp vụ kho: phiếu hủy hàng hỏng và kiểm kê. Chỉ dùng Eloquent, mỗi nghiệp vụ là một transaction. */
class InventoryService
{
    public const WASTE_REASONS = [
        'Hết hạn sử dụng',
        'Hư hỏng / ẩm mốc',
        'Rơi vỡ, đổ làm hỏng',
        'Lỗi sản xuất',
        'Khác',
    ];

    /** Nguyên nhân hao hụt để chọn trong mẫu biên bản giải trình. */
    public const SHORTAGE_CAUSES = [
        'Sai số cân đo / đong đếm',
        'Hao hụt khi chế biến',
        'Đổ vỡ, rơi vãi',
        'Hàng hết hạn chưa lập phiếu hủy',
        'Nhầm lẫn khi xuất / nhập kho',
        'Chưa rõ nguyên nhân',
    ];

    /** Dung sai mặc định (%) khi nguyên liệu không có tolerance_rate hoặc là thành phẩm. */
    public const DEFAULT_TOLERANCE = 5.0;

    // ----------------------------------------------------------------- PHIẾU HỦY
    /** @param array{item_type:string,lot_id?:?int,ingredient_id?:?int,quantity:int,reason_type:string} $data */
    public function recordWaste(array $data, User $user): InventoryWaste
    {
        return DB::transaction(function () use ($data, $user) {
            $now = now();
            $qty = (int) $data['quantity'];

            if ($data['item_type'] === 'Product') {
                $lot = ProductLot::lockForUpdate()->find($data['lot_id'] ?? 0);
                if (! $lot) {
                    throw ValidationException::withMessages(['lot_id' => 'Vui lòng chọn lô thành phẩm.']);
                }
                if ($qty > $lot->remaining_qty) {
                    throw ValidationException::withMessages(['quantity' => "Lô #{$lot->lot_id} chỉ còn {$lot->remaining_qty} sản phẩm."]);
                }
                $product = Product::with('boms.ingredient')->findOrFail($lot->product_id);
                $unitCost = $product->unitCost();

                $lot->decrement('remaining_qty', $qty);

                $ticket = InventoryWaste::create([
                    'product_id' => $lot->product_id, 'lot_id' => $lot->lot_id, 'ingredient_id' => null,
                    'ticket_date' => $now, 'quantity' => $qty, 'reason_type' => $data['reason_type'],
                    'user_id' => $user->user_id, 'item_type' => 'Product', 'unit' => 'cái',
                    'unit_cost' => $unitCost, 'total_cost' => $unitCost * $qty, 'approved_by' => null,
                ]);

                ProductStockMovement::create([
                    'product_id' => $lot->product_id, 'lot_id' => $lot->lot_id, 'movement_type' => 'Waste_Out',
                    'quantity' => $qty, 'unit_cost' => $unitCost, 'total_cost' => $unitCost * $qty,
                    'reference_type' => 'Waste', 'reference_id' => $ticket->ticket_id,
                    'movement_date' => $now, 'user_id' => $user->user_id, 'note' => $data['reason_type'],
                ]);
                ProductStockTransaction::create([
                    'product_id' => $lot->product_id, 'lot_id' => $lot->lot_id, 'transaction_type' => 'Waste',
                    'quantity' => $qty, 'reference_type' => 'Waste', 'reference_id' => $ticket->ticket_id,
                    'transaction_date' => $now,
                ]);

                return $ticket;
            }

            $ing = Ingredient::lockForUpdate()->find($data['ingredient_id'] ?? 0);
            if (! $ing) {
                throw ValidationException::withMessages(['ingredient_id' => 'Vui lòng chọn nguyên liệu.']);
            }
            if ($qty > $ing->stock_quantity) {
                throw ValidationException::withMessages(['quantity' => "{$ing->ingredient_name} chỉ còn {$ing->stock_quantity} {$ing->unit}."]);
            }

            $ing->decrement('stock_quantity', $qty);

            $ticket = InventoryWaste::create([
                'product_id' => null, 'lot_id' => null, 'ingredient_id' => $ing->ingredient_id,
                'ticket_date' => $now, 'quantity' => $qty, 'reason_type' => $data['reason_type'],
                'user_id' => $user->user_id, 'item_type' => 'Ingredient', 'unit' => $ing->unit,
                'unit_cost' => (int) $ing->cost_price_vn, 'total_cost' => (int) $ing->cost_price_vn * $qty, 'approved_by' => null,
            ]);

            IngredientStockTransaction::create([
                'ingredient_id' => $ing->ingredient_id, 'transaction_type' => 'Waste', 'quantity' => $qty,
                'unit' => $ing->unit, 'reference_type' => 'Waste', 'reference_id' => $ticket->ticket_id,
                'transaction_date' => $now, 'user_id' => $user->user_id,
            ]);

            return $ticket;
        });
    }

    // ----------------------------------------------------------------- KIỂM KÊ
    /** Mở phiên kiểm kê mới: chụp tồn hệ thống của toàn bộ nguyên liệu và các lô thành phẩm còn hàng. */
    public function startStocktake(User $user): StocktakeSession
    {
        return DB::transaction(function () use ($user) {
            if (StocktakeSession::where('status', StocktakeSession::IN_PROGRESS)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['stocktake' => 'Đang có một phiên kiểm kê chưa hoàn tất. Hãy hoàn tất phiên đó trước.']);
            }

            $now = now();
            $session = StocktakeSession::create([
                'stocktake_date' => $now,
                'session_name' => 'Kiểm kê '.$now->format('H:i d/m/Y'),
                'status' => StocktakeSession::IN_PROGRESS,
                'created_by' => $user->user_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (Ingredient::orderBy('ingredient_name')->get() as $ing) {
                StocktakeDetail::create([
                    'stocktake_id' => $session->stocktake_id, 'item_type' => 'Ingredient',
                    'ingredient_id' => $ing->ingredient_id, 'system_qty' => $ing->stock_quantity,
                    'unit' => $ing->unit, 'created_by' => $user->user_id, 'created_at' => $now,
                ]);
            }
            foreach (ProductLot::available()->orderBy('product_id')->orderBy('expiry_date')->get() as $lot) {
                StocktakeDetail::create([
                    'stocktake_id' => $session->stocktake_id, 'item_type' => 'Product',
                    'product_id' => $lot->product_id, 'lot_id' => $lot->lot_id, 'system_qty' => $lot->remaining_qty,
                    'unit' => 'cái', 'created_by' => $user->user_id, 'created_at' => $now,
                ]);
            }

            return $session;
        });
    }

    /**
     * Lưu số đếm thực tế; nếu $submit thì chốt phiên: tính chênh lệch, đối chiếu dung sai và
     *  - có dòng vượt dung sai => Pending_Approval (chờ Quản lý duyệt, CHƯA điều chỉnh tồn kho),
     *  - ngược lại            => Completed và điều chỉnh tồn kho theo số thực tế.
     *
     * @param  array<int, array{actual:?string,explanation:?string}>  $counts  [stocktake_detail_id => ...]
     */
    public function saveStocktake(StocktakeSession $session, array $counts, bool $submit, User $user): StocktakeSession
    {
        return DB::transaction(function () use ($session, $counts, $submit, $user) {
            $session = StocktakeSession::lockForUpdate()->findOrFail($session->stocktake_id);
            if ($session->status !== StocktakeSession::IN_PROGRESS) {
                throw ValidationException::withMessages(['stocktake' => 'Phiên kiểm kê đã chốt, không thể sửa.']);
            }

            $details = $session->details()->get()->keyBy('stocktake_detail_id');
            foreach ($counts as $id => $row) {
                $detail = $details[$id] ?? null;
                if (! $detail) {
                    continue;
                }
                $raw = $row['actual'] ?? null;
                $actual = ($raw === null || $raw === '') ? null : (int) $raw;
                $detail->update(['actual_qty' => $actual, 'explanation' => filled($row['explanation'] ?? null) ? $row['explanation'] : null]);
            }
            $session->update(['updated_at' => now()]);

            if (! $submit) {
                return $session;
            }

            $details = $session->details()->with(['ingredient', 'lot.product'])->get();
            $counted = $details->filter(fn ($d) => $d->actual_qty !== null);
            if ($counted->isEmpty()) {
                throw ValidationException::withMessages(['stocktake' => 'Chưa nhập số đếm nào. Hãy đếm ít nhất một mặt hàng.']);
            }

            $missing = [];
            $over = [];
            foreach ($counted as $d) {
                $variance = (int) $d->actual_qty - (int) $d->system_qty;
                $pct = $d->system_qty > 0 ? abs($variance) / $d->system_qty * 100 : ($variance !== 0 ? 100.0 : 0.0);
                $tol = $d->item_type === 'Ingredient' && $d->ingredient?->tolerance_rate !== null
                    ? (float) $d->ingredient->tolerance_rate : self::DEFAULT_TOLERANCE;

                $code = $variance === 0 ? 'MATCH' : ($pct <= $tol ? 'WITHIN_TOLERANCE' : 'OVER_TOLERANCE');
                if ($code === 'OVER_TOLERANCE') {
                    if (blank($d->explanation)) {
                        $missing[] = $d->label();
                    }
                    $over[] = $d;
                }

                $d->update([
                    'variance_qty' => $variance,
                    'variance_percentage' => round($pct, 2),
                    'reason_code' => $code,
                ]);
            }
            if ($missing) {
                throw ValidationException::withMessages([
                    'stocktake' => 'Các mặt hàng vượt dung sai bắt buộc có giải trình: '.implode(', ', $missing),
                ]);
            }

            // Dòng chưa đếm: bỏ khỏi phiên (không đối soát)
            $session->details()->whereNull('actual_qty')->delete();

            if ($over) {
                foreach ($over as $d) {
                    ShortageExplanation::create([
                        'stocktake_detail_id' => $d->stocktake_detail_id,
                        'variance_qty' => $d->variance_qty,
                        'explanation_note' => $d->explanation,
                        'created_by' => $user->user_id,
                        'approved_by' => null,
                        'created_at' => now(),
                    ]);
                }
                $session->update(['status' => StocktakeSession::PENDING_APPROVAL, 'updated_at' => now(),
                    'note' => count($over).' mặt hàng vượt dung sai, chờ Quản lý phê duyệt giải trình.']);

                return $session;
            }

            $this->applyAdjustments($counted, $user);
            $session->update(['status' => StocktakeSession::COMPLETED, 'updated_at' => now(),
                'note' => 'Đã điều chỉnh tồn kho theo số thực tế.']);

            return $session;
        });
    }

    // ----------------------------------------------------------------- BIÊN BẢN GIẢI TRÌNH & DUYỆT KIỂM KÊ
    /**
     * Lưu mẫu biên bản giải trình hao hụt cho các dòng vượt dung sai của phiên đang chờ duyệt.
     * Mỗi dòng gồm nguyên nhân (chọn từ danh sách), biện pháp khắc phục và ghi chú; được ghép thành một câu
     * "[nguyên nhân] Khắc phục: ... Ghi chú: ..." lưu vào shortage_explanations.explanation_note (và stocktake_details.explanation).
     *
     * @param  array<int, array{cause:?string,measure:?string,note:?string}>  $rows  [stocktake_detail_id => ...]
     */
    public function saveShortageReport(StocktakeSession $session, array $rows, User $user): void
    {
        DB::transaction(function () use ($session, $rows, $user) {
            $session = StocktakeSession::lockForUpdate()->findOrFail($session->stocktake_id);
            if ($session->status !== StocktakeSession::PENDING_APPROVAL) {
                throw ValidationException::withMessages(['report' => 'Chỉ lập/sửa biên bản khi phiên đang chờ Quản lý duyệt.']);
            }

            $details = $session->details()->where('reason_code', 'OVER_TOLERANCE')->get()->keyBy('stocktake_detail_id');
            $explanations = ShortageExplanation::whereIn('stocktake_detail_id', $details->keys())->get()->keyBy('stocktake_detail_id');

            foreach ($details as $id => $detail) {
                $row = $rows[$id] ?? null;
                $cause = trim((string) ($row['cause'] ?? ''));
                $measure = trim((string) ($row['measure'] ?? ''));
                $note = trim((string) ($row['note'] ?? ''));

                if (! in_array($cause, self::SHORTAGE_CAUSES, true)) {
                    throw ValidationException::withMessages(['report' => 'Vui lòng chọn nguyên nhân cho: '.$detail->label()]);
                }
                if ($measure === '') {
                    throw ValidationException::withMessages(['report' => 'Vui lòng nhập biện pháp khắc phục cho: '.$detail->label()]);
                }

                $text = '['.$cause.'] Khắc phục: '.$measure.($note !== '' ? '. Ghi chú: '.$note : '');
                $text = Str::limit($text, 250, '');

                $detail->update(['explanation' => $text]);

                $existing = $explanations->get($id);
                if ($existing) {
                    $existing->update(['explanation_note' => $text, 'variance_qty' => $detail->variance_qty]);
                } else {
                    ShortageExplanation::create([
                        'stocktake_detail_id' => $id, 'variance_qty' => $detail->variance_qty, 'explanation_note' => $text,
                        'created_by' => $user->user_id, 'approved_by' => null, 'created_at' => now(),
                    ]);
                }
            }

            $session->update(['updated_at' => now()]);
        });
    }

    /** Quản lý duyệt giải trình: điều chỉnh tồn kho theo số thực tế và đóng phiên. */
    public function approveStocktake(StocktakeSession $session, User $manager): StocktakeSession
    {
        return DB::transaction(function () use ($session, $manager) {
            $session = StocktakeSession::lockForUpdate()->findOrFail($session->stocktake_id);
            if ($session->status !== StocktakeSession::PENDING_APPROVAL) {
                throw ValidationException::withMessages(['stocktake' => 'Phiên này không ở trạng thái chờ duyệt.']);
            }

            $details = $session->details()->with('ingredient')->whereNotNull('actual_qty')->get();
            $this->applyAdjustments($details, $manager);

            ShortageExplanation::whereIn('stocktake_detail_id', $details->pluck('stocktake_detail_id'))
                ->update(['approved_by' => $manager->user_id]);

            $session->update([
                'status' => StocktakeSession::COMPLETED,
                'approved_by' => $manager->user_id,
                'updated_at' => now(),
                'note' => 'Quản lý đã duyệt giải trình và điều chỉnh tồn kho theo số thực tế.',
            ]);

            return $session;
        });
    }

    /** Quản lý từ chối giải trình: mở lại phiên để Staff đếm lại (xóa các giải trình chưa duyệt). */
    public function rejectStocktake(StocktakeSession $session, string $reason): StocktakeSession
    {
        return DB::transaction(function () use ($session, $reason) {
            $session = StocktakeSession::lockForUpdate()->findOrFail($session->stocktake_id);
            if ($session->status !== StocktakeSession::PENDING_APPROVAL) {
                throw ValidationException::withMessages(['stocktake' => 'Phiên này không ở trạng thái chờ duyệt.']);
            }

            $ids = $session->details()->pluck('stocktake_detail_id');
            ShortageExplanation::whereIn('stocktake_detail_id', $ids)->whereNull('approved_by')->delete();
            $session->details()->update(['variance_qty' => null, 'variance_percentage' => null, 'reason_code' => null]);

            $session->update([
                'status' => StocktakeSession::IN_PROGRESS,
                'updated_at' => now(),
                'note' => Str::limit('Quản lý yêu cầu kiểm đếm lại: '.$reason, 250, ''),
            ]);

            return $session;
        });
    }

    /** Giá vốn một đơn vị của dòng kiểm kê (dùng để quy ra giá trị thất thoát trong biên bản). */
    public function unitCostOf(StocktakeDetail $detail): int
    {
        if ($detail->item_type === 'Ingredient') {
            return (int) ($detail->ingredient->cost_price_vn ?? 0);
        }

        return $detail->lot?->product ? $detail->lot->product->unitCost() : 0;
    }

    /** Điều chỉnh tồn kho theo chênh lệch (không để âm) và ghi sổ giao dịch. */
    private function applyAdjustments($details, User $user): void
    {
        $now = now();
        foreach ($details as $d) {
            $variance = (int) $d->variance_qty;
            if ($variance === 0) {
                continue;
            }

            if ($d->item_type === 'Ingredient') {
                $ing = Ingredient::lockForUpdate()->find($d->ingredient_id);
                $ing->update(['stock_quantity' => max(0, $ing->stock_quantity + $variance)]);
                IngredientStockTransaction::create([
                    'ingredient_id' => $ing->ingredient_id,
                    'transaction_type' => $variance > 0 ? 'Stocktake_Gain' : 'Stocktake_Loss',
                    'quantity' => abs($variance), 'unit' => $ing->unit, 'reference_type' => 'Stocktake',
                    'reference_id' => $d->stocktake_id, 'transaction_date' => $now, 'user_id' => $user->user_id,
                ]);
            } else {
                $lot = ProductLot::lockForUpdate()->find($d->lot_id);
                $lot->update(['remaining_qty' => max(0, $lot->remaining_qty + $variance)]);
                ProductStockMovement::create([
                    'product_id' => $lot->product_id, 'lot_id' => $lot->lot_id,
                    'movement_type' => $variance > 0 ? 'Stocktake_Gain' : 'Stocktake_Loss',
                    'quantity' => abs($variance), 'unit_cost' => 0, 'total_cost' => 0,
                    'reference_type' => 'Stocktake', 'reference_id' => $d->stocktake_id,
                    'movement_date' => $now, 'user_id' => $user->user_id, 'note' => 'Điều chỉnh sau kiểm kê',
                ]);
                ProductStockTransaction::create([
                    'product_id' => $lot->product_id, 'lot_id' => $lot->lot_id, 'transaction_type' => 'Adjust',
                    'quantity' => abs($variance), 'reference_type' => 'Stocktake', 'reference_id' => $d->stocktake_id,
                    'transaction_date' => $now,
                ]);
            }
        }
    }
}
