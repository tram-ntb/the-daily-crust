<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\InventoryWaste;
use App\Models\ProductLot;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Phiếu hủy hàng hỏng (inventory_waste). */
class WasteController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $canWrite = $request->user()->role === 'staff';

        return view('staff.waste.index', [
            'tickets' => InventoryWaste::query()->with(['product', 'ingredient', 'user'])
                ->orderByDesc('ticket_date')->paginate(15),
            'canWrite' => $canWrite,
            'reasons' => InventoryService::WASTE_REASONS,
            'ingredients' => $canWrite ? Ingredient::orderBy('ingredient_name')->get(['ingredient_id', 'ingredient_name', 'unit', 'stock_quantity']) : collect(),
            'lots' => $canWrite ? ProductLot::available()->with('product:product_id,vi_name')->orderBy('expiry_date')->get() : collect(),
            'monthCost' => (int) InventoryWaste::whereMonth('ticket_date', now()->month)->whereYear('ticket_date', now()->year)->sum('total_cost'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'item_type' => ['required', Rule::in(['Product', 'Ingredient'])],
            'lot_id' => ['required_if:item_type,Product', 'nullable', 'integer'],
            'ingredient_id' => ['required_if:item_type,Ingredient', 'nullable', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason_type' => ['required', Rule::in(InventoryService::WASTE_REASONS)],
        ], [
            'lot_id.required_if' => 'Vui lòng chọn lô thành phẩm cần hủy.',
            'ingredient_id.required_if' => 'Vui lòng chọn nguyên liệu cần hủy.',
            'quantity.required' => 'Vui lòng nhập số lượng.',
            'quantity.min' => 'Số lượng phải lớn hơn 0.',
        ]);

        try {
            $ticket = $this->inventory->recordWaste($data, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.waste.index')->with('success', "Đã lập phiếu hủy #{$ticket->ticket_id} (chờ Quản lý duyệt).");
    }
}
