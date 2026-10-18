<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Dashboard vận hành cho STAFF và MANAGER (tách khỏi trang mua hàng của customer).
 * Route phải đi qua middleware CheckRole:manager,staff (xem admin_routes_snippet.php).
 *
 * Phân biệt theo vai trò (dữ liệu được lọc ở SERVER, không chỉ ẩn bằng CSS):
 *  - manager: doanh thu ngày + tuần kèm tăng trưởng, cảnh báo mua hàng/duyệt, doanh thu theo dòng sản phẩm,
 *             thông tin mô hình dự báo, nút "Chạy mô hình", "Lập phiếu mua NL", "Duyệt kế hoạch".
 *  - staff:   doanh thu ngày (không tăng trưởng/không tuần), sản lượng mẻ nướng, cảnh báo chỉ để xem,
 *             biểu đồ theo số lượng (không hiện tiền theo dòng sản phẩm), nút POS / Kiểm kê / Tiêu hủy / Ghi mẻ nướng.
 */
class AdminDashboardController extends Controller
{
    /** Trạng thái giao hàng được coi là đã xong (đoán theo dữ liệu mẫu: 'Delivered'). Chỉnh nếu bạn dùng giá trị khác. */
    private const DELIVERY_DONE = ['Delivered', 'Completed', 'Cancelled'];

    /** Trạng thái giao hàng cần can thiệp. */
    private const DELIVERY_PROBLEM = ['Failed', 'Returned', 'Delayed'];

    public function __invoke(Request $request): View
    {
        $isManager = $request->user()->role === 'manager';

        $bizDate = $this->businessDate();
        $prevDate = $this->previousTradingDate($bizDate);

        return view('admin.home', [
            'isManager' => $isManager,
            'bizDate' => $bizDate,
            'isToday' => $bizDate->isToday(),
            'prevDate' => $prevDate,
            'kpis' => $this->kpis($isManager, $bizDate, $prevDate),
            'alerts' => $this->alerts($isManager),
            'hourlyChart' => $this->hourlyChart($isManager, $bizDate),
            'categoryChart' => $this->categoryChart($isManager, $bizDate),
            'quickActions' => $this->quickActions($isManager),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Ngày nghiệp vụ */
    /* ------------------------------------------------------------------ */

    /**
     * Hôm nay nếu đã có đơn; nếu chưa (hoặc dữ liệu mẫu cũ) thì lấy ngày gần nhất có đơn (<= hôm nay).
     * Khi chạy thật, ngày nào cũng có đơn nên luôn là hôm nay.
     */
    private function businessDate(): Carbon
    {
        $today = Carbon::today();

        if ($this->ordersQuery()->whereBetween('order_datetime', $this->range($today))->exists()) {
            return $today;
        }

        $last = $this->ordersQuery()->where('order_datetime', '<', $today)->max('order_datetime');

        return $last ? Carbon::parse($last)->startOfDay() : $today;
    }

    /** Ngày có giao dịch gần nhất trước $date (để so sánh tăng trưởng, kể cả khi hôm qua nghỉ bán). */
    private function previousTradingDate(Carbon $date): ?Carbon
    {
        $last = $this->ordersQuery()->where('order_datetime', '<', $date->copy()->startOfDay())->max('order_datetime');

        return $last ? Carbon::parse($last)->startOfDay() : null;
    }

    private function range(Carbon $from, ?Carbon $to = null): array
    {
        return [$from->copy()->startOfDay(), ($to ?? $from)->copy()->endOfDay()];
    }

    /**
     * orders.order_status trong DB hiện toàn NULL nên chưa lọc được "hoàn thành".
     * Khi bạn nhập trạng thái đơn, thêm điều kiện lọc tại đây (một chỗ duy nhất).
     */
    private function ordersQuery()
    {
        return DB::table('orders');
    }

    /** order_details.quantity trong DB đang NULL: mỗi dòng chi tiết = 1 sản phẩm, doanh thu dòng = price. */
    private function linesQuery()
    {
        return DB::table('order_details as od')->join('orders as o', 'o.order_id', '=', 'od.order_id');
    }

    private function growth(float|int $now, float|int $before): ?float
    {
        return $before > 0 ? round(($now - $before) / $before * 100, 1) : null;
    }

    private function money(float|int $v): string
    {
        return number_format($v, 0, ',', '.').' đ';
    }

    /* ------------------------------------------------------------------ */
    /*  Khối 1: KPI */
    /* ------------------------------------------------------------------ */

    private function kpis(bool $isManager, Carbon $day, ?Carbon $prev): array
    {
        $dayRange = $this->range($day);
        $prevRange = $prev ? $this->range($prev) : null;

        $revenue = (int) $this->ordersQuery()->whereBetween('order_datetime', $dayRange)->sum('total_amount');
        $orders = $this->ordersQuery()->whereBetween('order_datetime', $dayRange)->count();
        $pieces = $this->linesQuery()->whereBetween('o.order_datetime', $dayRange)->count();

        $prevRevenue = $prevRange ? (int) $this->ordersQuery()->whereBetween('order_datetime', $prevRange)->sum('total_amount') : 0;
        $prevOrders = $prevRange ? $this->ordersQuery()->whereBetween('order_datetime', $prevRange)->count() : 0;
        $prevPieces = $prevRange ? $this->linesQuery()->whereBetween('o.order_datetime', $prevRange)->count() : 0;

        $vsLabel = $prev ? 'so với '.$prev->format('d/m') : 'chưa có ngày để so sánh';

        if ($isManager) {
            // Tuần này (từ thứ Hai đến ngày nghiệp vụ) so với cùng khoảng của tuần trước.
            $weekStart = $day->copy()->startOfWeek(Carbon::MONDAY);
            $weekRange = $this->range($weekStart, $day);
            $prevWeekRange = $this->range($weekStart->copy()->subWeek(), $day->copy()->subWeek());

            $weekRevenue = (int) $this->ordersQuery()->whereBetween('order_datetime', $weekRange)->sum('total_amount');
            $prevWeekRevenue = (int) $this->ordersQuery()->whereBetween('order_datetime', $prevWeekRange)->sum('total_amount');

            return [
                ['label' => 'Doanh thu hôm nay',        'value' => $this->money($revenue),     'growth' => $this->growth($revenue, $prevRevenue), 'sub' => $vsLabel],
                ['label' => 'Doanh thu tuần này',       'value' => $this->money($weekRevenue), 'growth' => $this->growth($weekRevenue, $prevWeekRevenue), 'sub' => 'so với tuần trước'],
                ['label' => 'Đơn hàng hoàn thành',      'value' => number_format($orders),     'growth' => $this->growth($orders, $prevOrders), 'sub' => $vsLabel],
                ['label' => 'Lượng bánh tiêu thụ thực tế', 'value' => number_format($pieces).' chiếc', 'growth' => $this->growth($pieces, $prevPieces), 'sub' => $vsLabel],
            ];
        }

        // Staff: không có doanh thu tuần/tăng trưởng doanh thu; thay bằng sản lượng mẻ nướng.
        $batch = DB::table('production_batches')
            ->whereBetween('batch_date', $dayRange)
            ->selectRaw('COALESCE(SUM(produced_qty),0) as produced, COALESCE(SUM(passed_qty),0) as passed')
            ->first();

        return [
            ['label' => 'Doanh thu hôm nay',   'value' => $this->money($revenue), 'growth' => null, 'sub' => 'ca hiện tại'],
            ['label' => 'Đơn hàng hoàn thành', 'value' => number_format($orders), 'growth' => $this->growth($orders, $prevOrders), 'sub' => $vsLabel],
            ['label' => 'Lượng bánh tiêu thụ thực tế', 'value' => number_format($pieces).' chiếc', 'growth' => $this->growth($pieces, $prevPieces), 'sub' => $vsLabel],
            ['label' => 'Mẻ nướng hôm nay',    'value' => number_format($batch->passed).' / '.number_format($batch->produced), 'growth' => null, 'sub' => 'đạt / đã nướng'],
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Khối 2: Cảnh báo */
    /* ------------------------------------------------------------------ */

    private function alerts(bool $isManager): array
    {
        $alerts = [];

        // 1) Nguyên liệu chạm mức tồn tối thiểu
        $lowQuery = DB::table('ingredients')->whereColumn('stock_quantity', '<=', 'min_stock_level');
        $lowCount = (clone $lowQuery)->count();
        if ($lowCount > 0) {
            $items = (clone $lowQuery)
                ->orderByRaw('stock_quantity / NULLIF(min_stock_level, 0) asc')
                ->limit(8)
                ->get(['ingredient_name', 'unit', 'stock_quantity', 'min_stock_level'])
                ->map(fn ($i) => [
                    'name' => $i->ingredient_name,
                    'text' => number_format($i->stock_quantity).' / '.number_format($i->min_stock_level).' '.$i->unit,
                    'ratio' => $i->min_stock_level > 0 ? min(100, round($i->stock_quantity / $i->min_stock_level * 100)) : 0,
                ])->all();

            $alerts[] = [
                'level' => 'danger', 'icon' => 'fa-cubes',
                'title' => "Nguyên liệu sắp hết: {$lowCount} mục chạm mức tồn tối thiểu",
                'url' => $this->link('admin.inventory.index'), 'cta' => 'Xem danh sách',
                'items' => $items,
            ];
        }

        // 2) Kế hoạch sản xuất ngày mai
        $tomorrow = Carbon::tomorrow();
        $plans = DB::table('production_plans')
            ->whereBetween('plan_date', $this->range($tomorrow))
            ->get(['plan_status', 'approved_at']);

        if ($plans->isEmpty()) {
            $alerts[] = [
                'level' => 'warning', 'icon' => 'fa-industry',
                'title' => 'Chưa có kế hoạch sản xuất cho ngày mai ('.$tomorrow->format('d/m').')',
                'url' => $this->link('admin.production.index'), 'cta' => $isManager ? 'Lập & chốt kế hoạch' : 'Xem kế hoạch', 'items' => [],
            ];
        } else {
            $unapproved = $plans->whereNull('approved_at')->count();
            if ($unapproved > 0) {
                $alerts[] = [
                    'level' => 'warning', 'icon' => 'fa-industry',
                    'title' => "Lịch sản xuất ngày mai chưa chốt: {$unapproved}/{$plans->count()} kế hoạch chờ duyệt",
                    'url' => $this->link('admin.production.index'), 'cta' => $isManager ? 'Duyệt ngay' : 'Xem kế hoạch', 'items' => [],
                ];
            }
            $shortage = $plans->where('plan_status', 'Adjusted_Shortage')->count();
            if ($shortage > 0) {
                $alerts[] = [
                    'level' => 'danger', 'icon' => 'fa-triangle-exclamation',
                    'title' => "Ngày mai có {$shortage} kế hoạch bị hạ sản lượng do thiếu nguyên liệu",
                    'url' => $this->link('admin.production.index'), 'cta' => 'Xem chi tiết', 'items' => [],
                ];
            }
        }

        // 3) Mua hàng chưa giải quyết — chỉ manager (liên quan chi phí/duyệt)
        if ($isManager) {
            $reqPending = DB::table('purchase_requisitions')->where('status', 'Pending')->count();
            if ($reqPending > 0) {
                $alerts[] = [
                    'level' => 'warning', 'icon' => 'fa-cart-flatbed',
                    'title' => "{$reqPending} phiếu đề nghị mua nguyên liệu đang chờ duyệt",
                    'url' => $this->link('admin.purchase.index'), 'cta' => 'Duyệt phiếu', 'items' => [],
                ];
            }
            $poPending = DB::table('purchase_orders')->where('po_status', 'Pending')->count();
            if ($poPending > 0) {
                $alerts[] = [
                    'level' => 'info', 'icon' => 'fa-truck-ramp-box',
                    'title' => "{$poPending} đơn mua nhà cung cấp chưa nhận hàng",
                    'url' => $this->link('admin.purchase.index'), 'cta' => 'Theo dõi', 'items' => [],
                ];
            }
        }

        // 4) Trạng thái đơn hàng (giao hàng chưa xong / có vấn đề / thanh toán lỗi)
        $pendingDelivery = DB::table('delivery_orders')
            ->where(fn ($q) => $q->whereNull('delivery_status')->orWhereNotIn('delivery_status', self::DELIVERY_DONE))
            ->count();
        $problem = DB::table('delivery_orders')->whereIn('delivery_status', self::DELIVERY_PROBLEM)->count()
                 + DB::table('payments')->whereIn('status', ['Failed', 'Pending'])->count();

        if ($pendingDelivery > 0 || $problem > 0) {
            $alerts[] = [
                'level' => $problem > 0 ? 'danger' : 'info', 'icon' => 'fa-file-invoice',
                'title' => "Đơn hàng: {$pendingDelivery} đang chờ xử lý".($problem > 0 ? ", {$problem} cần can thiệp" : ''),
                'url' => $this->link('admin.orders.index'), 'cta' => 'Xử lý đơn', 'items' => [],
            ];
        }

        return $alerts;
    }

    /* ------------------------------------------------------------------ */
    /*  Khối 3: Biểu đồ */
    /* ------------------------------------------------------------------ */

    /**
     * Dự báo vs thực tế theo giờ.
     * DB hiện chỉ có dự báo THEO NGÀY (production_forecasts), chưa có theo giờ, nên:
     *   1) Nếu có bảng `hourly_forecasts` (forecast_date, forecast_hour, predicted_qty, model_version) -> dùng kết quả ML của bạn.
     *   2) Nếu chưa -> ước tính nền: trung bình số bánh bán theo từng giờ của các ngày CÙNG LOẠI (Weekday/Weekend)
     *      trong 8 tuần trước. Đây KHÔNG phải mô hình hồi quy; biểu đồ ghi rõ nguồn.
     */
    private function hourlyChart(bool $isManager, Carbon $day): array
    {
        $actual = $this->linesQuery()
            ->whereBetween('o.order_datetime', $this->range($day))
            ->selectRaw('HOUR(o.order_datetime) as h, COUNT(*) as q')
            ->groupBy('h')->pluck('q', 'h')->all();

        $forecast = [];
        $source = null;

        if (Schema::hasTable('hourly_forecasts')) {
            $forecast = DB::table('hourly_forecasts')
                ->whereBetween('forecast_date', $this->range($day))
                ->selectRaw('forecast_hour as h, SUM(predicted_qty) as q')
                ->groupBy('forecast_hour')->pluck('q', 'h')->map(fn ($v) => (int) round($v))->all();
            $source = $forecast ? 'Mô hình ML (bảng hourly_forecasts)' : null;
        }

        if (! $forecast) {
            $base = $this->linesQuery()
                ->where('o.order_datetime', '>=', $day->copy()->subWeeks(8)->startOfDay())
                ->where('o.order_datetime', '<', $day->copy()->startOfDay())
                ->where('o.weekday_weekend', $day->isWeekend() ? 'Weekend' : 'Weekday');

            $days = (clone $base)->selectRaw('COUNT(DISTINCT DATE(o.order_datetime)) as d')->value('d');
            if ($days > 0) {
                $forecast = (clone $base)->selectRaw('HOUR(o.order_datetime) as h, COUNT(*) as q')
                    ->groupBy('h')->pluck('q', 'h')
                    ->map(fn ($v) => (int) round($v / $days))->all();
                $source = 'Ước tính nền: trung bình cùng loại ngày, 8 tuần gần nhất (chưa phải mô hình ML)';
            }
        }

        $allHours = array_merge(array_keys($actual), array_keys($forecast));
        $from = $allHours ? max(0, min(6, min($allHours))) : 6;
        $to = $allHours ? min(23, max(21, max($allHours))) : 21;

        $labels = $fc = $ac = [];
        for ($h = $from; $h <= $to; $h++) {
            $labels[] = sprintf('%d:00', $h);
            $fc[] = $forecast[$h] ?? 0;
            $ac[] = (int) ($actual[$h] ?? 0);
        }

        $model = null;
        if ($isManager) {
            $latest = DB::table('production_forecasts')->orderByDesc('created_at')->first(['model_version', 'confidence']);
            if ($latest) {
                $model = 'Mô hình '.$latest->model_version.' · độ tin cậy '.round($latest->confidence * 100).'%';
            }
        }

        return ['labels' => $labels, 'forecast' => $fc, 'actual' => $ac, 'source' => $source, 'model' => $model];
    }

    /** Manager: doanh thu theo dòng sản phẩm. Staff: chỉ số lượng bán (không lộ số tiền). */
    private function categoryChart(bool $isManager, Carbon $day): array
    {
        $rows = $this->linesQuery()
            ->join('products as p', 'p.product_id', '=', 'od.product_id')
            ->whereBetween('o.order_datetime', $this->range($day))
            ->selectRaw('p.category as category, SUM(od.price) as revenue, COUNT(*) as qty')
            ->groupBy('p.category')
            ->orderByDesc($isManager ? 'revenue' : 'qty')
            ->get();

        return [
            'metric' => $isManager ? 'revenue' : 'qty',
            'labels' => $rows->pluck('category')->all(),
            'values' => $rows->map(fn ($r) => (int) ($isManager ? $r->revenue : $r->qty))->all(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Khối 4: Lối tắt */
    /* ------------------------------------------------------------------ */

    private function quickActions(bool $isManager): array
    {
        if ($isManager) {
            return [
                ['label' => 'Chạy mô hình dự báo',      'icon' => 'fa-chart-line',     'url' => $this->link('admin.forecast.run'),     'method' => 'post', 'style' => 'primary'],
                ['label' => 'Lập phiếu mua nguyên liệu', 'icon' => 'fa-cart-flatbed',   'url' => $this->link('admin.purchase.create'),  'method' => 'get',  'style' => 'soft'],
                ['label' => 'Duyệt kế hoạch sản xuất',   'icon' => 'fa-clipboard-check', 'url' => $this->link('admin.production.index'), 'method' => 'get',  'style' => 'outline'],
                ['label' => 'Xem báo cáo ca làm việc',   'icon' => 'fa-clipboard-user', 'url' => $this->link('admin.shift-report'),     'method' => 'get',  'style' => 'outline'],
            ];
        }

        return [
            ['label' => 'Mở bán hàng POS',    'icon' => 'fa-cash-register',   'url' => $this->link('admin.pos'),             'method' => 'get', 'style' => 'primary'],
            ['label' => 'Ghi nhận mẻ nướng',  'icon' => 'fa-bread-slice',     'url' => $this->link('admin.production.index'), 'method' => 'get', 'style' => 'soft'],
            ['label' => 'Kiểm kê kho',        'icon' => 'fa-clipboard-check', 'url' => $this->link('admin.stocktake.index'),  'method' => 'get', 'style' => 'outline'],
            ['label' => 'Xem báo cáo ca làm việc', 'icon' => 'fa-clipboard-user', 'url' => $this->link('admin.shift-report'), 'method' => 'get', 'style' => 'outline'],
        ];
    }

    /** Trả về URL nếu route đã tồn tại, '#' nếu chưa (để trang không lỗi khi các chức năng chưa làm xong). */
    private function link(string $routeName): string
    {
        return Route::has($routeName) ? route($routeName) : '#';
    }
}
