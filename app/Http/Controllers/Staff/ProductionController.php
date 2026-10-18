<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\BillOfMaterial;
use App\Models\Ingredient;
use App\Models\IngredientStockTransaction;
use App\Models\InventoryWaste;
use App\Models\ProductionBatch;
use App\Models\ProductionPlan;
use App\Models\ProductLot;
use App\Models\ProductStockMovement;
use App\Models\ProductStockTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductionController extends Controller
{
    // Hiển thị danh sách kế hoạch sản xuất đầu ngày
    public function index(): View
    {
        $plans = ProductionPlan::with('product')->whereDate('plan_date', today())->get();

        return view('admin.production.index', compact('plans'));
    }

    public function acceptPlan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => 'required|integer|exists:production_plans,plan_id',
        ]);

        $plan = ProductionPlan::query()->findOrFail($validated['plan_id']);
        abort_if($plan->plan_status === 'Completed', 422, 'Kế hoạch này đã hoàn tất.');

        $plan->update([
            'plan_status' => 'Accepted',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Đã xác nhận tiếp nhận kế hoạch sản xuất.');
    }

    // Ghi nhận mẻ nướng thực tế (Thay thế AI Vision bằng nhập thủ công số lượng đạt & hỏng)
    public function storeBatch(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:production_plans,plan_id',
            'passed_qty' => 'required|integer|min:0',
            'failed_qty' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($request) {
            $plan = ProductionPlan::query()->with('product')->lockForUpdate()->findOrFail($request->plan_id);
            abort_if($plan->plan_status !== 'Accepted', 422, 'Cần xác nhận tiếp nhận kế hoạch trước khi ghi nhận mẻ nướng.');
            $totalProduced = $request->passed_qty + $request->failed_qty;

            // 1. Lưu nhật ký sản xuất (ProductionBatch)
            $batch = ProductionBatch::create([
                'batch_date' => now(),
                'plan_id' => $plan->plan_id,
                'product_id' => $plan->product_id,
                'produced_qty' => $totalProduced,
                'passed_qty' => $request->passed_qty,
                'failed_qty' => $request->failed_qty,
                'ai_image_url' => null, // Đã lược bỏ trạm AI Vision
                'user_id' => Auth::id(),
                'start_time' => now()->subHours(1)->format('H:i:s'),
                'end_time' => now()->format('H:i:s'),
                'status' => 'Completed',
            ]);

            // 2. Trừ kho nguyên liệu thô tự động theo định mức BOM
            $boms = BillOfMaterial::where('product_id', $plan->product_id)->get();
            foreach ($boms as $bom) {
                $totalRequiredQty = $bom->quantity_required * $totalProduced;

                $ingredient = Ingredient::query()->lockForUpdate()->findOrFail($bom->ingredient_id);
                abort_if(
                    $ingredient->stock_quantity < $totalRequiredQty,
                    422,
                    "Không đủ nguyên liệu {$ingredient->ingredient_name} để ghi nhận mẻ nướng."
                );
                $ingredient->decrement('stock_quantity', $totalRequiredQty);

                // Ghi nhận giao dịch kho nguyên liệu
                IngredientStockTransaction::create([
                    'ingredient_id' => $ingredient->ingredient_id,
                    'transaction_type' => 'Export',
                    'quantity' => $totalRequiredQty,
                    'unit' => $bom->unit,
                    'reference_type' => 'Production',
                    'reference_id' => $batch->batch_id,
                    'transaction_date' => now(),
                    'user_id' => Auth::id(),
                ]);
            }

            // 3. Cập nhật kho thành phẩm & Tạo Lô sản phẩm (ProductLot) nếu có bánh đạt chuẩn
            if ($request->passed_qty > 0) {
                $product = $plan->product;
                $expiryDays = (int) ($product->expiry_duration_day ?? 2);

                $lot = ProductLot::create([
                    'product_id' => $product->product_id,
                    'batch_id' => $batch->batch_id,
                    'production_date' => now(),
                    'expiry_date' => now()->addDays($expiryDays),
                    'initial_qty' => $request->passed_qty,
                    'remaining_qty' => $request->passed_qty,
                    'status' => 'Active',
                ]);

                // Ghi nhận giao dịch nhập kho thành phẩm
                ProductStockTransaction::create([
                    'product_id' => $product->product_id,
                    'lot_id' => $lot->lot_id,
                    'transaction_type' => 'Import',
                    'quantity' => $request->passed_qty,
                    'reference_type' => 'Production',
                    'reference_id' => $batch->batch_id,
                    'transaction_date' => now(),
                ]);

                ProductStockMovement::create([
                    'product_id' => $product->product_id,
                    'lot_id' => $lot->lot_id,
                    'movement_type' => 'Production_In',
                    'quantity' => $request->passed_qty,
                    'unit_cost' => $product->price * 0.5, // Giả định giá vốn
                    'total_cost' => ($product->price * 0.5) * $request->passed_qty,
                    'reference_type' => 'Batch',
                    'reference_id' => $batch->batch_id,
                    'movement_date' => now(),
                    'user_id' => Auth::id(),
                    'note' => 'Nhập kho thành phẩm từ mẻ nướng bếp',
                ]);
            }

            // 4. Nếu có bánh hỏng, ghi nhận vào bảng inventory_waste
            if ($request->failed_qty > 0) {
                $product = $plan->product;
                InventoryWaste::create([
                    'product_id' => $product->product_id,
                    'ticket_date' => now(),
                    'quantity' => $request->failed_qty,
                    'reason_type' => 'Lỗi nướng thủ công tại bếp',
                    'user_id' => Auth::id(),
                    'item_type' => 'Product',
                    'unit' => 'cái',
                    'unit_cost' => $product->price,
                    'total_cost' => $product->price * $request->failed_qty,
                ]);
            }

            $plan->update(['plan_status' => 'Completed']);
        });

        return redirect()->back()->with('success', 'Đã ghi nhận mẻ nướng, trừ kho nguyên liệu và cập nhật thành phẩm thành công!');
    }
}
