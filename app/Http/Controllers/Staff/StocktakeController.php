<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\StocktakeSession;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Kiểm kê: mở phiên -> đếm thực tế -> đối soát với hệ thống, vượt dung sai thì phải giải trình. */
class StocktakeController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request): View
    {
        return view('staff.stocktake.index', [
            'sessions' => StocktakeSession::query()->with('creator')->withCount('details')->orderByDesc('stocktake_date')->paginate(10),
            'open' => StocktakeSession::where('status', StocktakeSession::IN_PROGRESS)->first(),
            'canWrite' => $request->user()->role === 'staff',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $session = $this->inventory->startStocktake($request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('admin.stocktake.show', $session)->with('success', 'Đã mở phiên kiểm kê. Hãy đếm và nhập số thực tế.');
    }

    public function show(Request $request, StocktakeSession $stocktake): View
    {
        $details = $stocktake->details()->with(['ingredient', 'lot.product'])
            ->orderBy('item_type')->orderBy('stocktake_detail_id')->get();

        return view('staff.stocktake.show', [
            'session' => $stocktake,
            'details' => $details,
            'editable' => $stocktake->status === StocktakeSession::IN_PROGRESS && $request->user()->role === 'staff',
            'defaultTol' => InventoryService::DEFAULT_TOLERANCE,
            'overCount' => $details->where('reason_code', 'OVER_TOLERANCE')->count(),
            'isManager' => $request->user()->role === 'manager',
        ]);
    }

    /** Mẫu biên bản giải trình hao hụt: tự điền số liệu các dòng vượt dung sai, Staff chọn nguyên nhân + biện pháp. */
    public function report(Request $request, StocktakeSession $stocktake): View|RedirectResponse
    {
        $lines = $stocktake->details()->with(['ingredient', 'lot.product.boms.ingredient'])
            ->where('reason_code', 'OVER_TOLERANCE')->orderBy('item_type')->orderBy('stocktake_detail_id')->get();

        if ($lines->isEmpty()) {
            return redirect()->route('admin.stocktake.show', $stocktake)
                ->withErrors(['report' => 'Phiên này không có mặt hàng nào vượt dung sai nên không cần biên bản giải trình.']);
        }

        $explanations = $stocktake->shortageExplanations()->get()->keyBy('stocktake_detail_id');
        $rows = $lines->map(function ($d) use ($explanations) {
            $tol = $d->item_type === 'Ingredient' && $d->ingredient?->tolerance_rate !== null
                ? (float) $d->ingredient->tolerance_rate : InventoryService::DEFAULT_TOLERANCE;
            $variance = (int) $d->variance_qty;
            $text = (string) ($explanations->get($d->stocktake_detail_id)?->explanation_note ?? $d->explanation);
            $parsed = $this->parseReportText($text);

            return [
                'detail' => $d,
                'tolerance' => $tol,
                'variance' => $variance,
                'loss_value' => $variance < 0 ? abs($variance) * $this->inventory->unitCostOf($d) : 0,
                'cause' => $parsed['cause'],
                'measure' => $parsed['measure'],
                'note' => $parsed['note'],
                'raw' => $text,
                'filled' => $explanations->get($d->stocktake_detail_id)?->isReportFilled() ?? false,
            ];
        });

        return view('staff.stocktake.report', [
            'session' => $stocktake->load(['creator', 'approver']),
            'rows' => $rows,
            'causes' => InventoryService::SHORTAGE_CAUSES,
            'totalLoss' => (int) $rows->sum('loss_value'),
            'editable' => $stocktake->status === StocktakeSession::PENDING_APPROVAL && $request->user()->role === 'staff',
            'author' => $request->user(),
        ]);
    }

    public function saveReport(Request $request, StocktakeSession $stocktake): RedirectResponse
    {
        $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.cause' => ['required', Rule::in(InventoryService::SHORTAGE_CAUSES)],
            'rows.*.measure' => ['required', 'string', 'max:90'],
            'rows.*.note' => ['nullable', 'string', 'max:60'],
        ], [
            'rows.*.cause.required' => 'Vui lòng chọn nguyên nhân cho mọi dòng.',
            'rows.*.cause.in' => 'Nguyên nhân không hợp lệ.',
            'rows.*.measure.required' => 'Vui lòng nhập biện pháp khắc phục cho mọi dòng.',
        ]);

        try {
            $this->inventory->saveShortageReport($stocktake, $request->input('rows'), $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.stocktake.report', $stocktake)->with('success', 'Đã lưu biên bản giải trình. Quản lý sẽ xem xét và phê duyệt.');
    }

    /** Quản lý duyệt: điều chỉnh tồn kho theo số thực tế. */
    public function approve(Request $request, StocktakeSession $stocktake): RedirectResponse
    {
        try {
            $this->inventory->approveStocktake($stocktake, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('admin.stocktake.show', $stocktake)->with('success', 'Đã duyệt giải trình và điều chỉnh tồn kho theo số thực tế.');
    }

    /** Quản lý từ chối: mở lại phiên để đếm lại. */
    public function reject(Request $request, StocktakeSession $stocktake): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:150']], ['reason.required' => 'Vui lòng nhập lý do yêu cầu kiểm đếm lại.']);

        try {
            $this->inventory->rejectStocktake($stocktake, $data['reason']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('admin.stocktake.show', $stocktake)->with('success', 'Đã yêu cầu kiểm đếm lại. Staff cần đếm và chốt lại phiên.');
    }

    /** Tách "[nguyên nhân] Khắc phục: ... Ghi chú: ..." thành các trường để điền lại vào mẫu. */
    private function parseReportText(string $text): array
    {
        $out = ['cause' => '', 'measure' => '', 'note' => ''];
        if (preg_match('/^\[(.+?)\]\s*Khắc phục:\s*(.*?)(?:\. Ghi chú:\s*(.*))?$/u', $text, $m)) {
            $out['cause'] = $m[1];
            $out['measure'] = $m[2];
            $out['note'] = $m[3] ?? '';
        }

        return $out;
    }

    public function update(Request $request, StocktakeSession $stocktake): RedirectResponse
    {
        $request->validate([
            'counts' => ['required', 'array'],
            'counts.*.actual' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'counts.*.explanation' => ['nullable', 'string', 'max:200'],
            'action' => ['required', 'in:save,submit'],
        ], ['counts.*.actual.integer' => 'Số đếm phải là số nguyên không âm.']);

        try {
            $result = $this->inventory->saveStocktake($stocktake, $request->input('counts'), $request->input('action') === 'submit', $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        if ($request->input('action') === 'save') {
            return back()->with('success', 'Đã lưu số đếm tạm thời.');
        }

        $msg = $result->status === StocktakeSession::COMPLETED
            ? 'Kiểm kê hoàn tất, tồn kho đã được điều chỉnh theo số thực tế.'
            : 'Đã gửi kiểm kê. Có mặt hàng vượt dung sai nên đang chờ Quản lý phê duyệt giải trình.';

        return redirect()->route('admin.stocktake.show', $result)->with('success', $msg);
    }
}
