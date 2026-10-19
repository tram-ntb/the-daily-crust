<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\IngredientStockTransaction;
use App\Models\InventoryWaste;
use App\Models\ProductionBatch;
use App\Models\ProductionPlan;
use App\Models\ProductLot;
use App\Models\ProductStockMovement;
use App\Models\ProductStockTransaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nghiệp vụ sản xuất đầu ngày. Toàn bộ chỉ dùng Eloquent; mọi thay đổi kho nằm trong MỘT transaction.
 */
class ProductionService
{
    /** Số lượng còn được phép ghi nhận "đạt" của kế hoạch (kế hoạch - đã đạt từ các mẻ trước). */
    public function remainingQty(ProductionPlan $plan): int
    {
        return max(0, (int) $plan->planned_qty - (int) $plan->batches()->sum('passed_qty'));
    }

    /**
     * Nguyên liệu cần cho $produced sản phẩm theo BOM, kèm tồn kho hiện tại (dùng cho cảnh báo trên giao diện).
     *
     * @return Collection<int, array{ingredient_id:int,name:string,unit:string,per_unit:float,needed:int,stock:int,short:int}>
     */
    public function requirements(ProductionPlan $plan, int $produced): Collection
    {
        $plan->loadMissing('product.boms.ingredient');

        return $plan->product->boms->map(function ($bom) use ($produced) {
            $needed = (int) ceil(round((float) $bom->quantity_required * $produced, 4));
            $stock = (int) ($bom->ingredient->stock_quantity ?? 0);

            return [
                'ingredient_id' => (int) $bom->ingredient_id,
                'name' => $bom->ingredient->ingredient_name ?? ('NL #'.$bom->ingredient_id),
                'unit' => $bom->unit ?? ($bom->ingredient->unit ?? ''),
                'per_unit' => (float) $bom->quantity_required,
                'needed' => $needed,
                'stock' => $stock,
                'short' => max(0, $needed - $stock),
            ];
        })->values();
    }

    /** Staff (thợ chính) xác nhận đã nhận kế hoạch đã được Quản lý duyệt. */
    public function acknowledge(ProductionPlan $plan, User $user): void
    {
        DB::transaction(function () use ($plan, $user) {
            $locked = ProductionPlan::lockForUpdate()->findOrFail($plan->plan_id);

            if (! $locked->isApproved()) {
                throw ValidationException::withMessages(['plan' => 'Kế hoạch chưa được Quản lý duyệt.']);
            }
            if ($locked->isAcknowledged()) {
                throw ValidationException::withMessages(['plan' => 'Kế hoạch này đã được xác nhận trước đó.']);
            }

            $locked->update(['acknowledged_by' => $user->user_id, 'acknowledged_at' => now()]);
        });
    }

    /**
     * Ghi nhận một mẻ bánh: nhập số đạt / lỗi (thay cho AI Vision), trừ nguyên liệu theo BOM,
     * nhập kho thành phẩm đạt (tạo lô), ghi phiếu hủy cho phần lỗi và lưu lịch sử mẻ.
     */
    public function recordBatch(
        ProductionPlan $plan,
        int $passed,
        int $failed,
        ?string $failureNote,
        User $user,
        ?string $startTime = null,
        ?string $endTime = null,
    ): ProductionBatch {
        return DB::transaction(function () use ($plan, $passed, $failed, $failureNote, $user, $startTime, $endTime) {
            $plan = ProductionPlan::with('product.boms')->lockForUpdate()->findOrFail($plan->plan_id);

            if (! $plan->isApproved() || ! $plan->isAcknowledged()) {
                throw ValidationException::withMessages(['plan' => 'Kế hoạch phải được Quản lý duyệt và Staff xác nhận trước khi ghi nhận mẻ.']);
            }

            $produced = $passed + $failed;
            if ($produced <= 0) {
                throw ValidationException::withMessages(['passed_qty' => 'Cần nhập ít nhất 1 sản phẩm (đạt hoặc lỗi).']);
            }
            if ($failed > 0 && blank($failureNote)) {
                throw ValidationException::withMessages(['failure_note' => 'Vui lòng ghi chú nguyên nhân lỗi khi có sản phẩm lỗi.']);
            }

            $remaining = $this->remainingQty($plan);
            if ($passed > $remaining) {
                throw ValidationException::withMessages([
                    'passed_qty' => "Số đạt vượt kế hoạch: chỉ còn được ghi nhận tối đa {$remaining} sản phẩm.",
                ]);
            }

            $product = $plan->product;
            if ($product->boms->isEmpty()) {
                throw ValidationException::withMessages(['plan' => 'Sản phẩm chưa có công thức (BOM), không thể trừ nguyên liệu.']);
            }

            // Khóa các dòng nguyên liệu rồi kiểm tra đủ tồn
            $ingredients = Ingredient::whereIn('ingredient_id', $product->boms->pluck('ingredient_id'))
                ->lockForUpdate()->get()->keyBy('ingredient_id');

            $needs = [];
            $short = [];
            foreach ($product->boms as $bom) {
                $ing = $ingredients[$bom->ingredient_id];
                $need = (int) ceil(round((float) $bom->quantity_required * $produced, 4));
                $needs[$bom->ingredient_id] = $need;
                if ($ing->stock_quantity < $need) {
                    $short[] = "{$ing->ingredient_name} (cần {$need}, còn {$ing->stock_quantity} {$ing->unit})";
                }
            }
            if ($short) {
                throw ValidationException::withMessages(['plan' => 'Không đủ nguyên liệu: '.implode('; ', $short)]);
            }

            $now = now();
            $unitCost = $product->unitCost();

            // 1) Lưu lịch sử mẻ
            $batch = ProductionBatch::create([
                'batch_date' => $now,
                'plan_id' => $plan->plan_id,
                'product_id' => $plan->product_id,
                'produced_qty' => $produced,
                'passed_qty' => $passed,
                'failed_qty' => $failed,
                'ai_image_url' => null, // nhập tay, không dùng AI Vision
                'user_id' => $user->user_id,
                'start_time' => $startTime,
                'end_time' => $endTime ?: $now->format('H:i:s'),
                'status' => 'Completed',
                'failure_note' => $failed > 0 ? $failureNote : null,
            ]);

            // 2) Trừ nguyên liệu theo BOM + sổ giao dịch xuất kho
            foreach ($product->boms as $bom) {
                $need = $needs[$bom->ingredient_id];
                Ingredient::where('ingredient_id', $bom->ingredient_id)->decrement('stock_quantity', $need);

                IngredientStockTransaction::create([
                    'ingredient_id' => $bom->ingredient_id,
                    'transaction_type' => 'Export',
                    'quantity' => $need,
                    'unit' => $bom->unit ?? $ingredients[$bom->ingredient_id]->unit,
                    'reference_type' => 'Production',
                    'reference_id' => $batch->batch_id,
                    'transaction_date' => $now,
                    'user_id' => $user->user_id,
                ]);
            }

            // 3) Nhập kho thành phẩm đạt: tạo lô mới (hạn dùng = giờ làm xong + expiry_duration_day)
            if ($passed > 0) {
                $expiry = $now->copy()->addMinutes((int) round(((float) $product->expiry_duration_day ?: 1) * 24 * 60));

                $lot = ProductLot::create([
                    'product_id' => $plan->product_id,
                    'batch_id' => $batch->batch_id,
                    'production_date' => $now,
                    'expiry_date' => $expiry,
                    'initial_qty' => $passed,
                    'remaining_qty' => $passed,
                    'status' => ProductLot::STATUS_ACTIVE,
                ]);

                ProductStockMovement::create([
                    'product_id' => $plan->product_id,
                    'lot_id' => $lot->lot_id,
                    'movement_type' => 'Production_In',
                    'quantity' => $passed,
                    'unit_cost' => $unitCost,
                    'total_cost' => $unitCost * $passed,
                    'reference_type' => 'Batch',
                    'reference_id' => $batch->batch_id,
                    'movement_date' => $now,
                    'user_id' => $user->user_id,
                    'note' => 'Nhập kho mẻ bánh #'.$batch->batch_id,
                ]);

                ProductStockTransaction::create([
                    'product_id' => $plan->product_id,
                    'lot_id' => $lot->lot_id,
                    'transaction_type' => 'Import',
                    'quantity' => $passed,
                    'reference_type' => 'Production',
                    'reference_id' => $batch->batch_id,
                    'transaction_date' => $now,
                ]);
            }

            // 4) Phần lỗi: phiếu hủy tự động (chờ Quản lý duyệt => approved_by = null)
            if ($failed > 0) {
                InventoryWaste::create([
                    'product_id' => $plan->product_id,
                    'lot_id' => null,
                    'ingredient_id' => null,
                    'ticket_date' => $now,
                    'quantity' => $failed,
                    'reason_type' => 'Lỗi sản xuất: '.$failureNote,
                    'user_id' => $user->user_id,
                    'item_type' => 'Product',
                    'unit' => 'cái',
                    'unit_cost' => $unitCost,
                    'total_cost' => $unitCost * $failed,
                    'approved_by' => null,
                ]);
            }

            return $batch;
        });
    }
}
