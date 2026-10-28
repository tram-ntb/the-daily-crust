<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ProductionBatch;
use App\Models\ProductionPlan;
use App\Services\ProductionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Sản xuất đầu ngày: nhận kế hoạch đã duyệt -> nhập số đạt/lỗi từng mẻ (thay AI Vision) -> tự trừ NL & nhập kho. */
class ProductionController extends Controller
{
    /** Chỉ thợ chính được xác nhận kế hoạch. */
    private const ACKNOWLEDGE_POSITIONS = ['Thợ làm bánh chính'];

    /** Các vị trí được ghi nhận mẻ bánh. */
    private const KITCHEN_POSITIONS = ['Thợ làm bánh chính', 'Nhân viên phụ làm bánh', 'Thợ trang trí bánh'];

    public function __construct(private readonly ProductionService $production)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $date = $request->filled('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        $plans = ProductionPlan::query()
            ->with('product')
            ->whereHas('product', fn ($q) => $q->batchable())     // đồ uống pha theo đơn không qua màn hình ghi nhận mẻ
            ->withSum('batches as passed_total', 'passed_qty')
            ->withSum('batches as failed_total', 'failed_qty')
            ->whereDate('plan_date', $date)
            ->orderBy('plan_id')
            ->get()
            ->each(function (ProductionPlan $plan) {
                $plan->remaining = max(0, (int) $plan->planned_qty - (int) $plan->passed_total);
                $plan->shortages = $this->production->requirements($plan, $plan->remaining)->where('short', '>', 0)->values();
            });

        $recentDates = ProductionPlan::query()->select('plan_date')->distinct()->orderByDesc('plan_date')->limit(6)->pluck('plan_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString());

        $batches = ProductionBatch::query()->with(['product', 'user'])
            ->whereHas('product', fn ($q) => $q->batchable())
            ->orderByDesc('batch_date')->limit(15)->get();

        return view('staff.production.index', [
            'date' => $date,
            'plans' => $plans,
            'recentDates' => $recentDates,
            'batches' => $batches,
            'canAcknowledge' => $user->role === 'staff' && in_array($user->position, self::ACKNOWLEDGE_POSITIONS, true),
            'canRecord' => $user->role === 'staff' && in_array($user->position, self::KITCHEN_POSITIONS, true),
            'isStaff' => $user->role === 'staff',
        ]);
    }

    public function acknowledge(Request $request, ProductionPlan $plan): RedirectResponse
    {
        abort_unless(in_array($request->user()->position, self::ACKNOWLEDGE_POSITIONS, true), 403, 'Chỉ thợ làm bánh chính được xác nhận kế hoạch.');

        try {
            $this->production->acknowledge($plan, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Đã xác nhận kế hoạch sản xuất. Có thể bắt đầu ghi nhận mẻ bánh.');
    }

    public function create(Request $request, ProductionPlan $plan): View|RedirectResponse
    {
        abort_unless(in_array($request->user()->position, self::KITCHEN_POSITIONS, true), 403, 'Bạn không thuộc bộ phận làm bánh.');

        $plan->load('product');
        if ($plan->product->isMadeToOrder()) {
            return redirect()->route('admin.production.index', ['date' => $plan->plan_date->toDateString()])
                ->withErrors(['plan' => "{$plan->product->vi_name} là đồ uống pha theo đơn nên không ghi nhận theo mẻ."]);
        }
        if (! $plan->isApproved() || ! $plan->isAcknowledged()) {
            return redirect()->route('admin.production.index', ['date' => $plan->plan_date->toDateString()])
                ->withErrors(['plan' => 'Kế hoạch phải được duyệt và xác nhận trước khi ghi nhận mẻ.']);
        }

        $remaining = $this->production->remainingQty($plan);

        return view('staff.production.record', [
            'plan' => $plan,
            'remaining' => $remaining,
            'bom' => $this->production->requirements($plan, 1),   // định mức cho 1 sản phẩm + tồn hiện tại
            'failureTypes' => ProductionService::FAILURE_TYPES,
        ]);
    }

    public function store(Request $request, ProductionPlan $plan): RedirectResponse
    {
        abort_unless(in_array($request->user()->position, self::KITCHEN_POSITIONS, true), 403, 'Bạn không thuộc bộ phận làm bánh.');

        $data = $request->validate([
            'passed_qty' => ['required', 'integer', 'min:0', 'max:100000'],
            'failures' => ['nullable', 'array', 'max:10'],
            'failures.*.type' => ['nullable', Rule::in(ProductionService::FAILURE_TYPES)],
            'failures.*.qty' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'failure_note' => ['nullable', 'string', 'max:150'],
            'start_time' => ['nullable', 'date_format:H:i'],
        ], [
            'passed_qty.required' => 'Vui lòng nhập số lượng đạt (nhập 0 nếu không có).',
            'failures.*.type.in' => 'Loại lỗi không hợp lệ.',
        ]);

        // Dòng lỗi có số lượng nhưng chưa chọn loại => báo lỗi rõ ràng thay vì bỏ qua
        foreach ($data['failures'] ?? [] as $row) {
            if ((int) ($row['qty'] ?? 0) > 0 && blank($row['type'] ?? null)) {
                return back()->withErrors(['failures' => 'Vui lòng chọn loại lỗi cho mọi dòng có số lượng.'])->withInput();
            }
        }

        try {
            $batch = $this->production->recordBatch(
                $plan,
                (int) $data['passed_qty'],
                array_values($data['failures'] ?? []),
                $data['failure_note'] ?? null,
                $request->user(),
                filled($data['start_time'] ?? null) ? $data['start_time'].':00' : null,
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.production.index', ['date' => $plan->plan_date->toDateString()])
            ->with('success', "Đã ghi nhận mẻ #{$batch->batch_id}: {$batch->passed_qty} đạt, {$batch->failed_qty} lỗi. Đã trừ nguyên liệu và nhập kho thành phẩm.");
    }
}
