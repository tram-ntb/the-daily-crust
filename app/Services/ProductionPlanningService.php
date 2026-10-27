<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\ProductionForecast;
use App\Models\ProductionPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lập kế hoạch sản xuất hàng ngày từ kết quả dự báo (Phân hệ 4 ghi vào `production_forecasts`).
 * Quản lý xem dự báo + tồn thành phẩm + nguyên liệu theo BOM, điều chỉnh sản lượng rồi duyệt.
 * Chỉ dùng Eloquent.
 */
class ProductionPlanningService
{
    /** Giờ bắt đầu ngày kinh doanh: lô còn hạn sau mốc này được coi là tồn chuyển sang ngày kế hoạch. */
    public const BUSINESS_DAY_START_HOUR = 6;

    /** Ngày mặc định khi mở trang: ngày có dự báo gần nhất kể từ hôm nay, nếu chưa có thì ngày mai. */
    public function defaultDate(): Carbon
    {
        $next = ProductionForecast::production()->whereDate('forecast_date', '>=', today())->orderBy('forecast_date')->value('forecast_date');

        return $next ? Carbon::parse($next)->startOfDay() : today()->addDay();
    }

    /** Thông tin lần chạy dự báo của ngày (phiên bản mô hình, thời điểm tạo). */
    public function forecastMeta(Carbon $date): ?array
    {
        $rows = ProductionForecast::production()->whereDate('forecast_date', $date)->orderBy('forecast_id')->get();
        if ($rows->isEmpty()) {
            return null;
        }
        $last = $rows->last();

        return [
            'model' => $last->model_version,
            'created_at' => $last->created_at,
            'count' => $rows->unique('product_id')->count(),
        ];
    }

    /**
     * Mỗi dòng = một sản phẩm đang bán: dự báo, tồn chuyển sang, gợi ý, kế hoạch hiện có, trạng thái khóa và BOM.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(Carbon $date): Collection
    {
        $forecasts = ProductionForecast::production()->whereDate('forecast_date', $date)->orderBy('forecast_id')->get()->keyBy('product_id');
        $plans = ProductionPlan::query()->whereDate('plan_date', $date)->withCount('batches')->orderBy('plan_id')->get()->keyBy('product_id');

        $dayStart = $date->copy()->setTime(self::BUSINESS_DAY_START_HOUR, 0);
        $carry = ProductLot::available()
            ->where(function ($q) use ($dayStart) {
                $q->whereNull('expiry_date')->orWhere('expiry_date', '>', $dayStart);
            })
            ->get()->groupBy('product_id')->map(fn ($lots) => (int) $lots->sum('remaining_qty'));

        return Product::active()->producible()->with('boms.ingredient')->orderBy('category')->orderBy('vi_name')->get()
            ->map(function (Product $p) use ($forecasts, $plans, $carry) {
                $f = $forecasts->get($p->product_id);
                $plan = $plans->get($p->product_id);
                $forecast = $f?->predicted_qty;
                $stock = (int) ($carry[$p->product_id] ?? 0);

                return [
                    'product_id' => (int) $p->product_id,
                    'name' => $p->vi_name,
                    'sub' => $p->item,
                    'category' => $p->category,
                    'has_bom' => $p->boms->isNotEmpty(),
                    'forecast' => $forecast,
                    'confidence' => $f?->confidence,
                    'carry' => $stock,
                    'suggested' => $forecast === null ? null : max(0, $forecast - $stock),
                    'plan_id' => $plan?->plan_id,
                    'qty' => $plan ? (int) $plan->planned_qty : null,
                    'status' => $plan?->plan_status,
                    'reason' => $plan?->adjustment_reason,
                    'approved' => $plan?->approved_at !== null,
                    'acknowledged' => $plan?->acknowledged_at !== null,
                    'batches' => (int) ($plan?->batches_count ?? 0),
                    'locked' => $plan !== null && ($plan->acknowledged_at !== null || (int) $plan->batches_count > 0),
                    'bom' => $p->boms->map(fn ($b) => [
                        'ingredient_id' => (int) $b->ingredient_id,
                        'name' => $b->ingredient->ingredient_name ?? ('NL #'.$b->ingredient_id),
                        'unit' => $b->unit ?? ($b->ingredient->unit ?? ''),
                        'per_unit' => (float) $b->quantity_required,
                    ])->values()->all(),
                ];
            })
            ->sortBy(fn ($r) => ($r['forecast'] === null && $r['qty'] === null ? '1' : '0').'|'.$r['category'].'|'.$r['name'])
            ->values();
    }

    /** Tồn kho nguyên liệu hiện tại: [ingredient_id => tồn]. */
    public function ingredientStock(): array
    {
        return Ingredient::query()->pluck('stock_quantity', 'ingredient_id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Lưu kế hoạch của một ngày. Dòng có số lượng > 0 được thêm/cập nhật, số lượng 0 thì xóa kế hoạch cũ (nếu chưa khóa).
     * Kế hoạch đã được Staff xác nhận hoặc đã có mẻ thì KHÔNG sửa được.
     *
     * @param  array<int, array{qty:int|string|null, reason:?string}>  $input  [product_id => ...]
     * @return array{created:int, updated:int, deleted:int, skipped:int, approved:int}
     */
    public function save(Carbon $date, array $input, bool $approve, User $manager): array
    {
        if ($date->copy()->startOfDay()->lt(today())) {
            throw ValidationException::withMessages(['date' => 'Không thể lập hoặc sửa kế hoạch cho ngày đã qua.']);
        }

        return DB::transaction(function () use ($date, $input, $approve, $manager) {
            $products = Product::active()->producible()->with('boms')->whereIn('product_id', array_keys($input))->get()->keyBy('product_id');
            $forecasts = ProductionForecast::production()->whereDate('forecast_date', $date)->orderBy('forecast_id')->get()->keyBy('product_id');
            $plans = ProductionPlan::query()->whereDate('plan_date', $date)->withCount('batches')->lockForUpdate()->get()->keyBy('product_id');

            $stats = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0, 'approved' => 0];
            $errors = [];

            foreach ($input as $productId => $row) {
                $product = $products->get($productId);
                if (! $product) {
                    continue;
                }
                $plan = $plans->get($productId);
                $qty = (int) ($row['qty'] ?? 0);
                $reason = trim((string) ($row['reason'] ?? ''));

                if ($plan && ($plan->acknowledged_at !== null || (int) $plan->batches_count > 0)) {
                    $stats['skipped']++;   // đã khóa
                    continue;
                }

                if ($qty <= 0) {
                    if ($plan) {
                        $plan->delete();
                        $stats['deleted']++;
                    }
                    continue;
                }

                if ($product->boms->isEmpty()) {
                    $errors[] = "{$product->vi_name}: chưa có công thức BOM, hãy khai báo BOM trước khi lập kế hoạch.";
                    continue;
                }

                $forecast = $forecasts->get($productId)?->predicted_qty;
                if ($forecast !== null && $qty !== (int) $forecast && $reason === '') {
                    $errors[] = "{$product->vi_name}: sản lượng ({$qty}) khác dự báo ({$forecast}), vui lòng nhập lý do điều chỉnh.";
                    continue;
                }

                $status = $forecast === null ? 'Manual' : ($qty === (int) $forecast ? 'Original' : 'Adjusted_Manual');
                $payload = [
                    'plan_date' => $date->copy()->startOfDay(),
                    'product_id' => $productId,
                    'planned_qty' => $qty,
                    'forecasted_qty' => $forecast,
                    'adjusted_qty' => $qty,
                    'plan_status' => $status,
                    'adjustment_reason' => mb_substr($reason !== '' ? $reason : ($forecast === null ? 'Lập thủ công (chưa có dự báo)' : 'Giữ nguyên theo dự báo'), 0, 255),
                    'approved_by' => $approve ? $manager->user_id : null,
                    'approved_at' => $approve ? now() : null,
                ];

                if ($plan) {
                    $plan->update($payload);
                    $stats['updated']++;
                } else {
                    ProductionPlan::create($payload + ['created_by' => $manager->user_id]);
                    $stats['created']++;
                }
                if ($approve) {
                    $stats['approved']++;
                }
            }

            if ($errors) {
                throw ValidationException::withMessages(['plans' => $errors]);
            }

            return $stats;
        });
    }

    /** Duyệt một kế hoạch đã lập. */
    public function approve(ProductionPlan $plan, User $manager): void
    {
        DB::transaction(function () use ($plan, $manager) {
            $locked = ProductionPlan::lockForUpdate()->findOrFail($plan->plan_id);
            if ($locked->approved_at !== null) {
                throw ValidationException::withMessages(['plan' => 'Kế hoạch này đã được duyệt.']);
            }
            $locked->update(['approved_by' => $manager->user_id, 'approved_at' => now()]);
        });
    }

    /** Thu hồi duyệt (chỉ khi Staff chưa xác nhận và chưa có mẻ). */
    public function unapprove(ProductionPlan $plan): void
    {
        DB::transaction(function () use ($plan) {
            $locked = ProductionPlan::withCount('batches')->lockForUpdate()->findOrFail($plan->plan_id);
            if ($locked->acknowledged_at !== null || (int) $locked->batches_count > 0) {
                throw ValidationException::withMessages(['plan' => 'Kế hoạch đã được Staff xác nhận hoặc đã có mẻ nên không thể thu hồi duyệt.']);
            }
            $locked->update(['approved_by' => null, 'approved_at' => null]);
        });
    }
}
