<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductionForecast;
use App\Models\ProductLot;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class ForecastController extends Controller
{
    /** Trang "Kế hoạch sản xuất dự báo" của một ngày (mặc định: ngày mai, nếu chưa có thì ngày dự báo mới nhất). */
    public function index(Request $request): View
    {
        $dates = ProductionForecast::query()
            ->select('forecast_date')->distinct()
            ->orderByDesc('forecast_date')->limit(1500)   // toàn bộ ngày đã có dự báo, để lịch đánh dấu chấm
            ->pluck('forecast_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString());

        $tomorrow = Carbon::tomorrow()->toDateString();
        $date = $request->input('date');
        if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = $dates->contains($tomorrow) ? $tomorrow : ($dates->first() ?? $tomorrow);
        }

        // Hai nhóm dự báo nằm chung bảng, tách theo item_group:
        //  - Production: sản lượng 1 ngày của hàng làm tại bếp (biểu đồ, tổng, kế hoạch sản xuất)
        //  - Commercial: nhu cầu 7 ngày của hàng nhập từ NCC (chỉ dùng để gợi ý nhập hàng)
        $forecasts = ProductionForecast::production()
            ->whereDate('forecast_date', $date)
            ->orderByDesc('predicted_qty')
            ->get();

        $commercialForecasts = ProductionForecast::commercial()
            ->whereDate('forecast_date', $date)
            ->orderByDesc('predicted_qty')
            ->get();

        // Kế hoạch đã lập cho cùng ngày (nếu có) để đối chiếu với số dự báo
        $plans = DB::table('production_plans')
            ->whereDate('plan_date', $date)
            ->pluck('planned_qty', 'product_id');

        $products = DB::table('products')
            ->whereIn('product_id', $forecasts->pluck('product_id')->merge($commercialForecasts->pluck('product_id')))
            ->get(['product_id', 'vi_name', 'category'])
            ->keyBy('product_id');

        $rows = $forecasts->map(fn (ProductionForecast $f) => [
            'name'       => $products[$f->product_id]->vi_name ?? ('Sản phẩm #'.$f->product_id),
            'category'   => $products[$f->product_id]->category ?? null,
            'forecast'   => $f->predicted_qty,
            'confidence' => $f->confidence,
            'plan'       => $plans[$f->product_id] ?? null,
        ]);

        // Gợi ý nhập hàng = nhu cầu 7 ngày x (1 + dự phòng) - tồn bán được hiện tại (lô còn hạn)
        $safety = (float) config('forecast.restock_safety_ratio', 0.2);
        $stock = ProductLot::sellable()
            ->whereIn('product_id', $commercialForecasts->pluck('product_id'))
            ->get(['product_id', 'remaining_qty'])
            ->groupBy('product_id')
            ->map(fn ($lots) => (int) $lots->sum('remaining_qty'));

        $restock = $commercialForecasts->map(function (ProductionForecast $f) use ($products, $stock, $safety) {
            $days = max(1, (int) ($f->horizon_days ?: 7));
            $have = (int) ($stock[$f->product_id] ?? 0);
            $target = (int) ceil($f->predicted_qty * (1 + $safety));

            return [
                'name'       => $products[$f->product_id]->vi_name ?? ('Sản phẩm #'.$f->product_id),
                'category'   => $products[$f->product_id]->category ?? null,
                'demand'     => $f->predicted_qty,
                'days'       => $days,
                'per_day'    => round($f->predicted_qty / $days, 1),
                'stock'      => $have,
                'cover_days' => $f->predicted_qty > 0 ? round($have / ($f->predicted_qty / $days), 1) : null,
                'suggest'    => max(0, $target - $have),
                'confidence' => $f->confidence,
            ];
        })->sortByDesc('suggest')->values();

        $latest = $forecasts->merge($commercialForecasts)->sortByDesc('created_at')->first();

        return view('admin.forecast.index', [
            'date'       => Carbon::parse($date),
            'dates'      => $dates,
            'rows'       => $rows,
            'restock'    => $restock,
            'safety'     => $safety,
            'total'      => $forecasts->sum('predicted_qty'),
            'model'      => $latest?->model_version,
            'createdAt'  => $latest?->created_at,
            'isTomorrow' => $date === $tomorrow,
            'chart'      => [
                'labels' => $rows->take(12)->pluck('name')->values(),
                'data'   => $rows->take(12)->pluck('forecast')->values(),
            ],
        ]);
    }

    /** Nút "Chạy mô hình dự báo": gọi script Python ngay (dùng khi demo, không cần chờ 21:00). */
    public function run(Request $request): RedirectResponse
    {
        // Dùng đúng lệnh Artisan của hệ thống (cùng đường chạy với lịch 21:00, tự truyền nhóm hàng từ config)
        $exit = Artisan::call('app:run-production-forecast');
        if ($exit !== 0) {
            return back()->with('error', 'Chạy dự báo thất bại: '.str(trim(Artisan::output()))->limit(400));
        }

        return redirect()->route('admin.forecast.index')->with('success', 'Đã chạy mô hình: cập nhật dự báo sản xuất ngày mai và gợi ý nhập hàng 7 ngày.');
    }
}
