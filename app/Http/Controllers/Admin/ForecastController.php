<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductionForecast;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\View\View;

class ForecastController extends Controller
{
    /** Trang "Kế hoạch sản xuất dự báo" của một ngày (mặc định: ngày mai, nếu chưa có thì ngày dự báo mới nhất). */
    public function index(Request $request): View
    {
        $dates = ProductionForecast::query()
            ->select('forecast_date')->distinct()
            ->orderByDesc('forecast_date')->limit(30)
            ->pluck('forecast_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString());

        $tomorrow = Carbon::tomorrow()->toDateString();
        $date = $request->input('date');
        if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = $dates->contains($tomorrow) ? $tomorrow : ($dates->first() ?? $tomorrow);
        }

        $forecasts = ProductionForecast::query()
            ->whereDate('forecast_date', $date)
            ->orderByDesc('predicted_qty')
            ->get();

        // Kế hoạch đã lập cho cùng ngày (nếu có) để đối chiếu với số dự báo
        $plans = DB::table('production_plans')
            ->whereDate('plan_date', $date)
            ->pluck('planned_qty', 'product_id');

        $products = DB::table('products')
            ->whereIn('product_id', $forecasts->pluck('product_id'))
            ->get(['product_id', 'vi_name', 'category'])
            ->keyBy('product_id');

        $rows = $forecasts->map(fn (ProductionForecast $f) => [
            'name' => $products[$f->product_id]->vi_name ?? ('Sản phẩm #'.$f->product_id),
            'category' => $products[$f->product_id]->category ?? null,
            'forecast' => $f->predicted_qty,
            'confidence' => $f->confidence,
            'plan' => $plans[$f->product_id] ?? null,
        ]);

        $latest = $forecasts->sortByDesc('created_at')->first();

        return view('admin.forecast.index', [
            'date' => Carbon::parse($date),
            'dates' => $dates,
            'rows' => $rows,
            'total' => $forecasts->sum('predicted_qty'),
            'model' => $latest?->model_version,
            'createdAt' => $latest?->created_at,
            'isTomorrow' => $date === $tomorrow,
            'chart' => [
                'labels' => $rows->take(12)->pluck('name')->values(),
                'data' => $rows->take(12)->pluck('forecast')->values(),
            ],
        ]);
    }

    /** Nút "Chạy mô hình dự báo": gọi script Python ngay (dùng khi demo, không cần chờ 21:00). */
    public function run(Request $request): RedirectResponse
    {
        $script = config('forecast.script');
        if (! is_file($script)) {
            return back()->with('error', 'Không tìm thấy script Python: '.$script);
        }

        $result = Process::path(base_path())
            ->env(['PYTHONIOENCODING' => 'utf-8'])
            ->timeout(config('forecast.timeout'))
            ->run([config('forecast.python'), $script, '--env-file', base_path('.env')]);

        if ($result->failed()) {
            return back()->with('error', 'Chạy dự báo thất bại: '.str($result->errorOutput() ?: $result->output())->limit(400));
        }

        return redirect()->route('admin.forecast.index')->with('success', 'Đã chạy mô hình và cập nhật dự báo ngày mai.');
    }
}
