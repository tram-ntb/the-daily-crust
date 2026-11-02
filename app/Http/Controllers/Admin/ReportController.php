<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/** Báo cáo gọn: doanh thu / sản phẩm bán chạy / hao hụt / ĐỘ CHÍNH XÁC DỰ BÁO; và báo cáo ca làm việc. */
class ReportController extends Controller
{
    private const DAY_START_HOUR = 6;   // trùng cách tính "ngày bán hàng" của mô hình dự báo

    private function notCancelled($q)
    {
        return $q->where(fn ($w) => $w->whereNull('status')->orWhere('status', '!=', 'Cancelled'));
    }

    public function revenue(Request $request): View
    {
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->startOfDay() : Carbon::today();
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : $to->copy()->subDays(6);
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        $end = $to->copy()->addDay();

        $orders = fn () => $this->notCancelled(Order::query())->where('order_datetime', '>=', $from)->where('order_datetime', '<', $end);

        $byDay = $orders()->selectRaw('DATE(order_datetime) d, COUNT(*) orders, SUM(total_amount) revenue')->groupBy('d')->orderBy('d')->get();
        $totalRevenue = (int) $byDay->sum('revenue');
        $totalOrders = (int) $byDay->sum('orders');

        $channels = $orders()->selectRaw('order_channel ch, COUNT(*) orders, SUM(total_amount) revenue')->groupBy('ch')->get();

        $top = DB::table('order_details as d')->join('orders as o', 'o.order_id', '=', 'd.order_id')
            ->join('products as p', 'p.product_id', '=', 'd.product_id')
            ->where('o.order_datetime', '>=', $from)->where('o.order_datetime', '<', $end)
            ->where(fn ($w) => $w->whereNull('o.status')->orWhere('o.status', '!=', 'Cancelled'))
            ->selectRaw('p.vi_name name, SUM(COALESCE(d.quantity,1)) units, SUM(d.price * COALESCE(d.quantity,1)) revenue')
            ->groupBy('p.product_id', 'p.vi_name')->orderByDesc('units')->limit(8)->get();

        $waste = (int) DB::table('inventory_waste')->where('ticket_date', '>=', $from)->where('ticket_date', '<', $end)->sum('total_cost');

        return view('admin.reports.revenue', [
            'from' => $from, 'to' => $to,
            'byDay' => $byDay, 'maxRevenue' => max(1, (int) $byDay->max('revenue')),
            'totalRevenue' => $totalRevenue, 'totalOrders' => $totalOrders,
            'avgOrder' => $totalOrders ? (int) round($totalRevenue / $totalOrders) : 0,
            'channels' => $channels, 'top' => $top, 'waste' => $waste,
            'accuracy' => $this->forecastAccuracy($from, $to),
        ]);
    }

    /** So dự báo (nhóm sản xuất, mỗi ngày) với lượng bán thực tế theo "ngày bán hàng". */
    private function forecastAccuracy(Carbon $from, Carbon $to): array
    {
        $q = DB::table('production_forecasts')->whereDate('forecast_date', '>=', $from)->whereDate('forecast_date', '<=', $to);
        if (Schema::hasColumn('production_forecasts', 'item_group')) {
            $q->where('item_group', 'Production');
        }
        $forecasts = $q->get(['forecast_date', 'product_id', 'predicted_qty', 'model_version']);
        if ($forecasts->isEmpty()) {
            return ['days' => collect(), 'wape' => null, 'mae' => null, 'model' => null, 'rows' => 0];
        }

        $h = self::DAY_START_HOUR;
        $actual = DB::table('order_details as d')->join('orders as o', 'o.order_id', '=', 'd.order_id')
            ->whereIn('d.product_id', $forecasts->pluck('product_id')->unique())
            ->where('o.order_datetime', '>=', $from->copy()->addHours($h))->where('o.order_datetime', '<', $to->copy()->addDay()->addHours($h))
            ->where(fn ($w) => $w->whereNull('o.status')->orWhere('o.status', '!=', 'Cancelled'))
            ->selectRaw("DATE(DATE_SUB(o.order_datetime, INTERVAL {$h} HOUR)) d, d.product_id pid, SUM(COALESCE(d.quantity,1)) units")
            ->groupBy('d', 'pid')->get()->mapWithKeys(fn ($r) => [$r->d.'|'.$r->pid => (float) $r->units]);

        $rows = $forecasts->map(function ($f) use ($actual) {
            $d = Carbon::parse($f->forecast_date)->toDateString();

            return ['date' => $d, 'pred' => (int) $f->predicted_qty, 'act' => (float) ($actual[$d.'|'.$f->product_id] ?? 0)];
        });
        // Chỉ so sánh những ngày ĐÃ có dữ liệu bán thực tế (ngày dự báo cho tương lai chưa có đối chiếu)
        $daysWithActual = $rows->groupBy('date')->filter(fn ($g) => $g->sum('act') > 0)->keys();
        $rows = $rows->whereIn('date', $daysWithActual)->values();

        $sumAct = max(1e-9, (float) $rows->sum('act'));
        $days = $rows->groupBy('date')->map(fn ($g, $d) => [
            'date' => $d, 'pred' => (int) $g->sum('pred'), 'act' => (int) round($g->sum('act')),
            'wape' => $g->sum('act') > 0 ? abs($g->sum('pred') - $g->sum('act')) / $g->sum('act') : null,
        ])->values();

        return [
            'days' => $days,
            'wape' => $rows->isEmpty() ? null : $rows->sum(fn ($r) => abs($r['pred'] - $r['act'])) / $sumAct,
            'mae' => $rows->isEmpty() ? null : $rows->avg(fn ($r) => abs($r['pred'] - $r['act'])),
            'model' => $forecasts->pluck('model_version')->filter()->countBy()->sortDesc()->keys()->first(),
            'rows' => $rows->count(),
        ];
    }

    /** Báo cáo ca làm việc theo ngày: mỗi nhân viên bán POS bao nhiêu, làm bao nhiêu mẻ, lập bao nhiêu phiếu hủy. */
    public function shift(Request $request): View
    {
        $date = $request->filled('date') ? Carbon::parse($request->input('date'))->startOfDay() : Carbon::today();
        $next = $date->copy()->addDay();

        $pos = DB::table('orders')->where('order_channel', Order::CHANNEL_POS)->where('order_datetime', '>=', $date)->where('order_datetime', '<', $next)
            ->where(fn ($w) => $w->whereNull('status')->orWhere('status', '!=', 'Cancelled'))
            ->selectRaw('created_by uid, COUNT(*) orders, SUM(total_amount) revenue')->groupBy('created_by')->get()->keyBy('uid');
        $batches = DB::table('production_batches')->where('batch_date', '>=', $date)->where('batch_date', '<', $next)
            ->selectRaw('user_id uid, COUNT(*) batches, SUM(passed_qty) passed, SUM(failed_qty) failed')->groupBy('user_id')->get()->keyBy('uid');
        $waste = DB::table('inventory_waste')->where('ticket_date', '>=', $date)->where('ticket_date', '<', $next)
            ->selectRaw('user_id uid, COUNT(*) tickets, SUM(total_cost) cost')->groupBy('user_id')->get()->keyBy('uid');

        $ids = $pos->keys()->merge($batches->keys())->merge($waste->keys())->filter()->unique();
        $users = DB::table('users')->whereIn('user_id', $ids)->get(['user_id', 'full_name', 'position'])->keyBy('user_id');

        $rows = $ids->map(fn ($id) => [
            'name' => $users[$id]->full_name ?? "#{$id}", 'position' => $users[$id]->position ?? '',
            'orders' => (int) ($pos[$id]->orders ?? 0), 'revenue' => (int) ($pos[$id]->revenue ?? 0),
            'batches' => (int) ($batches[$id]->batches ?? 0), 'passed' => (int) ($batches[$id]->passed ?? 0), 'failed' => (int) ($batches[$id]->failed ?? 0),
            'tickets' => (int) ($waste[$id]->tickets ?? 0), 'cost' => (int) ($waste[$id]->cost ?? 0),
        ])->sortBy('name')->values();

        return view('admin.reports.shift', ['date' => $date, 'rows' => $rows]);
    }
}
