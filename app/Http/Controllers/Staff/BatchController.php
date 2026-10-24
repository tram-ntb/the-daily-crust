<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\InventoryWaste;
use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\ProductionForecast;
use App\Models\ProductionPlan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Theo dõi mẻ nướng thực tế: lịch sử mẻ + lô thành phẩm, thống kê loại lỗi, đối chiếu Dự báo - Kế hoạch - Thực tế. */
class BatchController extends Controller
{
    /** Khoảng lọc tối đa (ngày) để bảng đối chiếu theo ngày không quá dài. */
    private const MAX_DAYS = 31;

    public function index(Request $request): View
    {
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : today()->endOfDay();
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : $to->copy()->subDays(6)->startOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }
        if ($from->diffInDays($to) > self::MAX_DAYS) {
            $from = $to->copy()->subDays(self::MAX_DAYS)->startOfDay();
        }
        $productId = $request->integer('product_id') ?: null;

        $base = ProductionBatch::query()
            ->whereBetween('batch_date', [$from, $to])
            ->when($productId, fn ($q) => $q->where('product_id', $productId));

        $batches = (clone $base)->with(['product', 'user', 'lot'])->orderByDesc('batch_date')->paginate(12)->withQueryString();

        $produced = (int) (clone $base)->sum('produced_qty');
        $passed = (int) (clone $base)->sum('passed_qty');
        $failed = (int) (clone $base)->sum('failed_qty');

        return view('staff.batches.index', [
            'from' => $from,
            'to' => $to,
            'productId' => $productId,
            'products' => Product::active()->orderBy('vi_name')->get(['product_id', 'vi_name']),
            'batches' => $batches,
            'totals' => [
                'batches' => (clone $base)->count(),
                'produced' => $produced,
                'passed' => $passed,
                'failed' => $failed,
                'rate' => $produced > 0 ? round($passed / $produced * 100, 1) : null,
            ],
            'failureStats' => $this->failureStats($from, $to, $productId),
            'daily' => $this->dailyComparison($from, $to, $productId),
        ]);
    }

    /** Thống kê loại lỗi từ các phiếu hủy tự sinh khi ghi mẻ ("Lỗi sản xuất: <loại>"). */
    private function failureStats(Carbon $from, Carbon $to, ?int $productId)
    {
        $tickets = InventoryWaste::query()
            ->where('reason_type', 'like', 'Lỗi sản xuất:%')
            ->whereBetween('ticket_date', [$from, $to])
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->get();

        return $tickets
            ->groupBy(fn (InventoryWaste $t) => trim(explode(' – ', Str::after($t->reason_type, 'Lỗi sản xuất:'))[0]))
            ->map(fn ($group, $type) => ['type' => $type, 'qty' => (int) $group->sum('quantity'), 'cost' => (int) $group->sum('total_cost')])
            ->sortByDesc('qty')
            ->values();
    }

    /** Mỗi ngày: dự báo, kế hoạch, số đạt, số lỗi => biết dự báo/kế hoạch lệch bao nhiêu so với thực tế. */
    private function dailyComparison(Carbon $from, Carbon $to, ?int $productId)
    {
        $forecasts = ProductionForecast::query()->whereBetween('forecast_date', [$from, $to])
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->orderBy('forecast_id')->get()
            ->groupBy(fn ($f) => $f->forecast_date->toDateString())
            ->map(fn ($day) => (int) $day->unique('product_id')->sum('predicted_qty'));

        $plans = ProductionPlan::query()->whereBetween('plan_date', [$from, $to])
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->get()->groupBy(fn ($p) => $p->plan_date->toDateString())
            ->map(fn ($day) => (int) $day->sum('planned_qty'));

        $batches = ProductionBatch::query()->whereBetween('batch_date', [$from, $to])
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->get()->groupBy(fn ($b) => $b->batch_date->toDateString());

        $rows = collect();
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            $dayBatches = $batches->get($key, collect());
            $row = [
                'date' => $d->copy(),
                'forecast' => $forecasts->get($key),
                'planned' => $plans->get($key),
                'passed' => (int) $dayBatches->sum('passed_qty'),
                'failed' => (int) $dayBatches->sum('failed_qty'),
            ];
            if ($row['forecast'] !== null || $row['planned'] !== null || $dayBatches->isNotEmpty()) {
                $rows->push($row);
            }
        }

        return $rows->sortByDesc(fn ($r) => $r['date']->timestamp)->values();
    }
}
