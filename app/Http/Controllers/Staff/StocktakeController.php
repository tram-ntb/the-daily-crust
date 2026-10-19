<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\StocktakeSession;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);
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
