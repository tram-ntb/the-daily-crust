<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductionPlan;
use App\Services\NotifyService;
use App\Services\ProductionPlanningService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Lập & duyệt kế hoạch sản xuất hằng ngày dựa trên dự báo (CHỈ Manager, chặn ở route). */
class ProductionPlanController extends Controller
{
    public function __construct(
        private readonly ProductionPlanningService $planning,
        private readonly NotifyService $notify,
    ) {}

    public function index(Request $request): View
    {
        $date = $request->filled('date') ? Carbon::parse($request->input('date'))->startOfDay() : $this->planning->defaultDate();

        return view('admin.plans.index', [
            'date' => $date,
            'rows' => $this->planning->rows($date),
            'ingredientStock' => $this->planning->ingredientStock(),
            'meta' => $this->planning->forecastMeta($date),
            'isPast' => $date->lt(today()),
            'quickDates' => collect(range(0, 4))->map(fn ($i) => today()->addDays($i)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'action' => ['required', 'in:draft,approve'],
            'rows' => ['required', 'array'],
            'rows.*.qty' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'rows.*.reason' => ['nullable', 'string', 'max:255'],
        ], [
            'rows.*.qty.integer' => 'Sản lượng phải là số nguyên không âm.',
        ]);

        $date = Carbon::parse($data['date'])->startOfDay();

        try {
            $stats = $this->planning->save($date, $data['rows'], $data['action'] === 'approve', $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        if ($stats['approved'] > 0) {
            $this->notifyKitchen($date, (int) $stats['approved']);
        }

        $msg = "Đã lưu kế hoạch ngày {$date->format('d/m/Y')}: thêm {$stats['created']}, sửa {$stats['updated']}, xóa {$stats['deleted']}";
        $msg .= $stats['approved'] ? ", duyệt {$stats['approved']} kế hoạch." : '. Kế hoạch ở trạng thái nháp, chưa duyệt.';
        if ($stats['skipped']) {
            $msg .= " ({$stats['skipped']} kế hoạch đã được Staff xác nhận/ghi mẻ nên giữ nguyên.)";
        }

        return redirect()->route('admin.plans.index', ['date' => $date->toDateString()])->with('success', $msg);
    }

    public function approve(Request $request, ProductionPlan $plan): RedirectResponse
    {
        try {
            $this->planning->approve($plan, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $this->notifyKitchen($plan->plan_date->copy()->startOfDay(), 1);

        return back()->with('success', "Đã duyệt kế hoạch #{$plan->plan_id}.");
    }

    public function unapprove(ProductionPlan $plan): RedirectResponse
    {
        try {
            $this->planning->unapprove($plan);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', "Đã thu hồi duyệt kế hoạch #{$plan->plan_id}.");
    }

    /** Báo bộ phận bếp: kế hoạch sản xuất đã được duyệt, vào xác nhận và bắt đầu làm. */
    private function notifyKitchen(Carbon $date, int $count): void
    {
        $this->notify->toKitchen(
            'Kế hoạch sản xuất đã được duyệt',
            "Kế hoạch ngày {$date->format('d/m/Y')} ({$count} kế hoạch) đã được Quản lý duyệt. Hãy xác nhận và bắt đầu sản xuất.",
            route('admin.production.index', ['date' => $date->toDateString()], false),
            'fa-calendar-check',
            'emerald',
            'plan_approved'
        );
    }
}
