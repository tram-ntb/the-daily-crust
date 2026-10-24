<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ProductionForecast;
use App\Models\ProductLot;
use App\Services\ExpiryService;
use App\Services\InventoryService;
use App\Services\ProductionPlanningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Phân loại thành phẩm tồn đọng (quá hạn / cận date / còn tốt) và lập phiếu hủy. Staff và Manager xem, CHỈ Staff hủy. */
class ExpiryController extends Controller
{
    public function __construct(
        private readonly ExpiryService $expiry,
        private readonly ProductionPlanningService $planning,
    ) {}

    public function index(Request $request): View
    {
        $all = $this->expiry->lots();
        $state = (string) $request->input('state', 'attention');   // attention = quá hạn + cận date

        $lots = match ($state) {
            ExpiryService::STATE_EXPIRED, ExpiryService::STATE_NEAR, ExpiryService::STATE_OK => $all->where('state', $state),
            'all' => $all,
            default => $all->whereIn('state', [ExpiryService::STATE_EXPIRED, ExpiryService::STATE_NEAR]),
        };

        $by = fn (string $s) => $all->where('state', $s);

        // Gắn với Phân hệ 4: dự báo cần sản xuất của ngày kế hoạch gần nhất, giúp quyết định hủy hay giảm sản lượng.
        $planDate = $this->planning->defaultDate();
        $forecast = ProductionForecast::query()->whereDate('forecast_date', $planDate)->orderBy('forecast_id')->get()
            ->keyBy('product_id')->map(fn ($f) => (int) $f->predicted_qty);

        return view('staff.expiry.index', [
            'lots' => $lots->values(),
            'state' => $state,
            'summary' => [
                'expired' => ['lots' => $by('expired')->count(), 'qty' => (int) $by('expired')->sum(fn ($r) => $r['lot']->remaining_qty), 'value' => (int) $by('expired')->sum('value')],
                'near' => ['lots' => $by('near')->count(), 'qty' => (int) $by('near')->sum(fn ($r) => $r['lot']->remaining_qty), 'value' => (int) $by('near')->sum('value')],
                'ok' => ['lots' => $by('ok')->count(), 'qty' => (int) $by('ok')->sum(fn ($r) => $r['lot']->remaining_qty), 'value' => (int) $by('ok')->sum('value')],
            ],
            'forecast' => $forecast,
            'planDate' => $planDate,
            'reasons' => InventoryService::WASTE_REASONS,
            'nearPercent' => (int) (ExpiryService::NEAR_RATIO * 100),
            'canWrite' => $request->user()->role === 'staff',
        ]);
    }

    public function destroy(Request $request, ProductLot $lot): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason_type' => ['required', Rule::in(InventoryService::WASTE_REASONS)],
        ], ['quantity.required' => 'Vui lòng nhập số lượng cần hủy.']);

        try {
            $ticket = $this->expiry->destroyLot($lot->lot_id, (int) $data['quantity'], $data['reason_type'], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', "Đã lập phiếu hủy #{$ticket->ticket_id}: {$ticket->quantity} sản phẩm (chờ Quản lý duyệt).");
    }

    public function destroyExpired(Request $request): RedirectResponse
    {
        try {
            $r = $this->expiry->destroyAllExpired($request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', "Đã hủy {$r['quantity']} sản phẩm quá hạn ({$r['tickets']} phiếu, giá trị ".number_format($r['cost']).'đ).');
    }
}
