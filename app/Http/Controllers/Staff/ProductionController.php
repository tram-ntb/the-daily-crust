<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ProductionBatch;
use App\Models\ProductionPlan;
use App\Services\ProductionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductionController extends Controller
{
    public function __construct(private readonly ProductionService $production) {}

    public function index(Request $request): View
    {
        $date = $this->selectedDate($request);
        $plans = ProductionPlan::query()
            ->with('product.boms.ingredient')
            ->whereDate('plan_date', $date)
            ->orderBy('plan_id')
            ->get();

        $plans->each(function (ProductionPlan $plan): void {
            $plan->passed_total = (int) $plan->batches()->sum('passed_qty');
            $plan->failed_total = (int) $plan->batches()->sum('failed_qty');
            $plan->remaining = $this->production->remainingQty($plan);
            $plan->shortages = $this->production
                ->requirements($plan, $plan->remaining)
                ->filter(fn (array $requirement): bool => $requirement['short'] > 0)
                ->values();
        });

        $recentDates = ProductionPlan::query()
            ->selectRaw('DATE(plan_date) as plan_date')
            ->distinct()
            ->orderByDesc('plan_date')
            ->limit(30)
            ->pluck('plan_date');

        $batches = ProductionBatch::query()
            ->with(['product', 'user'])
            ->whereDate('batch_date', $date)
            ->latest('batch_date')
            ->limit(20)
            ->get();

        return view('staff.production.index', [
            'date' => $date,
            'plans' => $plans,
            'recentDates' => $recentDates,
            'batches' => $batches,
            'isStaff' => $request->user()->role === 'staff',
            'canAcknowledge' => $request->user()->role === 'staff',
            'canRecord' => $request->user()->role === 'staff',
        ]);
    }

    public function acknowledge(Request $request, ProductionPlan $plan): RedirectResponse
    {
        try {
            $this->production->acknowledge($plan, $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()
            ->route('admin.production.index', ['date' => $plan->plan_date->toDateString()])
            ->with('success', 'Đã xác nhận đã nhận kế hoạch sản xuất.');
    }

    public function create(ProductionPlan $plan): View
    {
        $plan->load('product');

        return view('staff.production.record', [
            'plan' => $plan,
            'remaining' => $this->production->remainingQty($plan),
            'bom' => $this->production->requirements($plan, 1),
        ]);
    }

    public function store(Request $request, ProductionPlan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'passed_qty' => ['required', 'integer', 'min:0'],
            'failed_qty' => ['required', 'integer', 'min:0'],
            'failure_note' => ['nullable', 'string', 'max:200'],
            'start_time' => ['nullable', 'date_format:H:i'],
        ]);

        try {
            $this->production->recordBatch(
                $plan,
                $validated['passed_qty'],
                $validated['failed_qty'],
                $validated['failure_note'] ?? null,
                $request->user(),
                $validated['start_time'] ?? null,
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('admin.production.index', ['date' => $plan->plan_date->toDateString()])
            ->with('success', 'Đã ghi nhận mẻ bánh và cập nhật tồn kho thành công.');
    }

    private function selectedDate(Request $request): Carbon
    {
        $date = $request->input('date');

        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        }

        return today();
    }
}
