<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\IngredientStockTransaction;
use App\Models\ProductionPlan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionDetail;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Mua nguyên liệu (CHỈ Manager): đề xuất từ thiếu hụt (kế hoạch sản xuất + tồn tối thiểu) -> lập đơn mua NCC -> nhận hàng (cộng kho).
 * Đây là mắt xích khép kín chuỗi: Dự báo -> Kế hoạch -> Thiếu nguyên liệu -> Đơn mua -> Nhập kho -> Sản xuất.
 */
class PurchaseController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->input('status', 'all');

        $orders = PurchaseOrder::query()->with(['supplier', 'creator', 'details.ingredient'])
            ->when(in_array($status, [PurchaseOrder::PENDING, PurchaseOrder::RECEIVED, PurchaseOrder::CANCELLED], true), fn ($q) => $q->where('po_status', $status))
            ->orderByDesc('po_id')->paginate(15)->withQueryString();

        return view('admin.purchase.index', [
            'orders' => $orders,
            'status' => $status,
            'counts' => PurchaseOrder::query()->selectRaw('po_status, COUNT(*) c')->groupBy('po_status')->pluck('c', 'po_status'),
        ]);
    }

    public function create(Request $request): View
    {
        $date = $request->filled('date') ? Carbon::parse($request->input('date')) : Carbon::tomorrow();

        // Nhu cầu nguyên liệu theo BOM của các kế hoạch sản xuất trong ngày đã chọn
        $need = [];
        ProductionPlan::query()->with('product.boms')->whereDate('plan_date', $date)->get()->each(function ($plan) use (&$need) {
            foreach ($plan->product?->boms ?? [] as $bom) {
                $need[$bom->ingredient_id] = ($need[$bom->ingredient_id] ?? 0) + (float) $bom->quantity_required * (int) $plan->planned_qty;
            }
        });

        $rows = Ingredient::orderBy('category')->orderBy('ingredient_name')->get()->map(function ($i) use ($need) {
            $stock = (int) $i->stock_quantity;
            $min = (int) $i->min_stock_level;
            $forPlan = max(0, (int) ceil($need[$i->ingredient_id] ?? 0) - $stock);       // thiếu để làm đủ kế hoạch
            $forMin = $stock < $min ? max(0, $min * 2 - $stock) : 0;                      // dưới tồn tối thiểu: bù lên 2 lần mức tối thiểu
            $suggest = max($forPlan, $forMin);

            return [
                'id' => (int) $i->ingredient_id, 'name' => $i->ingredient_name, 'category' => $i->category ?? 'Khác', 'unit' => $i->unit,
                'stock' => $stock, 'min' => $min, 'price' => (int) $i->cost_price_vn,
                'plan_need' => (int) ceil($need[$i->ingredient_id] ?? 0),
                'reason' => $forPlan > 0 && $forPlan >= $forMin ? 'Thiếu cho kế hoạch' : ($forMin > 0 ? 'Dưới tồn tối thiểu' : ''),
                'qty' => $suggest > 0 ? $suggest : '',
            ];
        })->sortBy(fn ($r) => [$r['reason'] === '' ? 1 : 0, $r['category'], $r['name']])->values();

        return view('admin.purchase.create', [
            'rows' => $rows,
            'suppliers' => Supplier::orderBy('company_name')->get(),
            'date' => $date,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,supplier_id'],
            'expected_delivery_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'rows' => ['required', 'array'],
            'rows.*.qty' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ], ['supplier_id.required' => 'Vui lòng chọn nhà cung cấp.']);

        $lines = collect($data['rows'])->map(fn ($r, $id) => ['id' => (int) $id, 'qty' => (int) ($r['qty'] ?? 0)])->where('qty', '>', 0);
        if ($lines->isEmpty()) {
            return back()->withInput()->withErrors(['rows' => 'Hãy nhập số lượng cho ít nhất một nguyên liệu.']);
        }

        $po = DB::transaction(function () use ($data, $lines, $request) {
            $ings = Ingredient::whereIn('ingredient_id', $lines->pluck('id'))->get()->keyBy('ingredient_id');
            $supplier = Supplier::find($data['supplier_id']);
            $poId = (int) DB::table('purchase_orders')->lockForUpdate()->max('po_id') + 1;
            $detailId = (int) DB::table('purchase_order_details')->lockForUpdate()->max('detail_id');

            $expected = $data['expected_delivery_date'] ?? now()->addDays((int) ($supplier->lead_time_days ?? 1));
            $now = now();
            $uid = $request->user()->user_id;

            // Phiếu đề xuất (purchase_requisitions): chụp lại tồn kho/tối thiểu tại thời điểm lập làm căn cứ cho đơn mua
            $reqId = (int) DB::table('purchase_requisitions')->lockForUpdate()->max('requisition_id') + 1;
            $reqDetailId = (int) DB::table('purchase_requisition_details')->lockForUpdate()->max('detail_id');

            $total = 0;
            foreach ($lines as $l) {
                $ing = $ings[$l['id']] ?? null;
                if (! $ing) {
                    continue;
                }
                $price = (int) $ing->cost_price_vn;
                PurchaseOrderDetail::create([
                    'detail_id' => ++$detailId, 'po_id' => $poId, 'ingredient_id' => $ing->ingredient_id,
                    'ordered_qty' => $l['qty'], 'received_qty' => 0, 'unit' => $ing->unit,
                    'unit_price' => $price, 'discount' => 0, 'tax' => 0, 'total_price' => $price * $l['qty'],
                ]);
                PurchaseRequisitionDetail::create([
                    'detail_id' => ++$reqDetailId, 'requisition_id' => $reqId, 'ingredient_id' => $ing->ingredient_id,
                    'current_stock' => (int) $ing->stock_quantity, 'min_stock' => (int) $ing->min_stock_level,
                    'requested_qty' => $l['qty'], 'unit' => $ing->unit,
                    'estimated_unit_price' => $price, 'estimated_total_price' => $price * $l['qty'],
                ]);
                $total += $price * $l['qty'];
            }

            PurchaseRequisition::create([
                'requisition_id' => $reqId, 'requisition_date' => $now, 'status' => PurchaseRequisition::APPROVED,
                'reason' => Str::limit('Đơn mua #'.$poId.': '.(filled($data['note'] ?? null) ? $data['note'] : 'bổ sung nguyên liệu theo gợi ý thiếu hụt'), 250, ''),
                'supplier_id' => $data['supplier_id'], 'expected_delivery_date' => $expected,
                'created_by' => $uid, 'approved_by' => $uid, 'approved_at' => $now, 'created_at' => $now,
            ]);

            $po = PurchaseOrder::create([
                'po_id' => $poId, 'supplier_id' => $data['supplier_id'], 'order_date' => $now,
                'expected_delivery_date' => $expected,
                'total_amount' => $total, 'currency' => 'VND', 'note' => $data['note'] ?? null,
                'approved_by' => $uid, 'po_status' => PurchaseOrder::PENDING, 'created_by' => $uid,
            ]);
            $po->requisition_id = $reqId;

            return $po;
        });

        return redirect()->route('admin.purchase.index')->with('success', "Đã lập phiếu đề xuất #{$po->requisition_id} và đơn mua #{$po->po_id} ({$po->details()->count()} nguyên liệu, ".number_format($po->total_amount).'đ).');
    }

    /** Nhận hàng: cộng tồn nguyên liệu theo số thực nhận và ghi sổ nhập kho. */
    public function receive(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $received = (array) $request->input('received', []);

        try {
            DB::transaction(function () use ($order, $received, $request) {
                $po = PurchaseOrder::lockForUpdate()->findOrFail($order->po_id);
                if ($po->po_status !== PurchaseOrder::PENDING) {
                    throw ValidationException::withMessages(['po' => 'Đơn mua này không ở trạng thái chờ nhận hàng.']);
                }
                $now = now();
                foreach ($po->details()->get() as $d) {
                    $qty = max(0, (int) ($received[$d->detail_id] ?? $d->ordered_qty));
                    $d->update(['received_qty' => $qty]);
                    if ($qty > 0) {
                        Ingredient::where('ingredient_id', $d->ingredient_id)->increment('stock_quantity', $qty);
                        IngredientStockTransaction::create([
                            'ingredient_id' => $d->ingredient_id, 'transaction_type' => 'Import', 'quantity' => $qty, 'unit' => $d->unit,
                            'reference_type' => 'PurchaseOrder', 'reference_id' => $po->po_id, 'transaction_date' => $now, 'user_id' => $request->user()->user_id,
                        ]);
                    }
                }
                $po->update(['po_status' => PurchaseOrder::RECEIVED]);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', "Đã nhận hàng đơn mua #{$order->po_id}; tồn nguyên liệu đã được cộng vào kho.");
    }

    public function cancel(PurchaseOrder $order): RedirectResponse
    {
        if ($order->po_status !== PurchaseOrder::PENDING) {
            return back()->withErrors(['po' => 'Chỉ hủy được đơn đang chờ nhận hàng.']);
        }
        $order->update(['po_status' => PurchaseOrder::CANCELLED]);

        return back()->with('success', "Đã hủy đơn mua #{$order->po_id}.");
    }
}
