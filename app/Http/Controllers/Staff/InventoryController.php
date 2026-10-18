<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\IngredientStockTransaction;
use App\Models\InventoryWaste;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\ProductStockMovement;
use App\Models\StocktakeDetail;
use App\Models\StocktakeSession;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(): View
    {
        $ingredients = Ingredient::query()->orderBy('ingredient_name')->get();
        $products = Product::query()->where('status', 'Active')->orderBy('vi_name')->get();
        $suppliers = Supplier::query()->where('status', 'ACTIVE')->orderBy('company_name')->get();

        return view('admin.inventory.index', compact('ingredients', 'products', 'suppliers'));
    }

    public function wasteIndex(): View
    {
        $products = Product::query()->where('status', 'Active')->orderBy('vi_name')->get();
        $ingredients = Ingredient::query()->orderBy('ingredient_name')->get();
        $wastes = InventoryWaste::query()
            ->with(['product', 'ingredient'])
            ->latest('ticket_date')
            ->paginate(15);

        return view('admin.inventory.waste', compact('products', 'ingredients', 'wastes'));
    }

    public function stocktakeIndex(): View
    {
        $ingredients = Ingredient::query()->orderBy('ingredient_name')->get();
        $sessions = StocktakeSession::query()->latest('stocktake_date')->paginate(15);

        return view('admin.inventory.stocktake', compact('ingredients', 'sessions'));
    }

    public function storeWaste(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_type' => 'required|in:Product,Ingredient',
            'quantity' => 'required|numeric|min:0.1',
            'reason_type' => 'required|string|max:255',
            'product_id' => 'required_if:item_type,Product|nullable|integer|exists:products,product_id',
            'ingredient_id' => 'required_if:item_type,Ingredient|nullable|integer|exists:ingredients,ingredient_id',
        ]);

        DB::transaction(function () use ($validated): void {
            $quantity = (float) $validated['quantity'];
            $product = null;
            $ingredient = null;

            if ($validated['item_type'] === 'Product') {
                $product = Product::query()->findOrFail($validated['product_id']);
                $remaining = $quantity;
                $lots = ProductLot::query()
                    ->where('product_id', $product->product_id)
                    ->where('status', 'Active')
                    ->where('remaining_qty', '>', 0)
                    ->orderBy('expiry_date')
                    ->lockForUpdate()
                    ->get();

                foreach ($lots as $lot) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $lotQuantity = (float) $lot->remaining_qty;
                    $deducted = min($remaining, $lotQuantity);
                    $lot->decrement('remaining_qty', $deducted);
                    if ($lotQuantity <= $deducted) {
                        $lot->update(['status' => 'Depleted']);
                    }

                    ProductStockMovement::query()->create([
                        'product_id' => $product->product_id,
                        'lot_id' => $lot->lot_id,
                        'movement_type' => 'Waste',
                        'quantity' => $deducted,
                        'unit_cost' => (float) $product->price,
                        'total_cost' => (float) $product->price * $deducted,
                        'reference_type' => 'InventoryWaste',
                        'movement_date' => now(),
                        'user_id' => Auth::id(),
                        'note' => $validated['reason_type'],
                    ]);
                    $remaining -= $deducted;
                }

                abort_if($remaining > 0, 422, 'Số lượng tiêu hủy vượt quá tồn kho thành phẩm.');
                $unit = 'cái';
                $unitCost = (float) $product->price;
            } else {
                $ingredient = Ingredient::query()->lockForUpdate()->findOrFail($validated['ingredient_id']);
                abort_if($ingredient->stock_quantity < $quantity, 422, 'Số lượng tiêu hủy vượt quá tồn kho nguyên liệu.');
                $ingredient->decrement('stock_quantity', $quantity);
                IngredientStockTransaction::query()->create([
                    'ingredient_id' => $ingredient->ingredient_id,
                    'transaction_type' => 'Waste',
                    'quantity' => $quantity,
                    'unit' => $ingredient->unit,
                    'reference_type' => 'InventoryWaste',
                    'transaction_date' => now(),
                    'user_id' => Auth::id(),
                ]);
                $unit = $ingredient->unit;
                $unitCost = (float) $ingredient->cost_price_vn;
            }

            InventoryWaste::query()->create([
                'product_id' => $product?->product_id,
                'ingredient_id' => $ingredient?->ingredient_id,
                'ticket_date' => now(),
                'quantity' => $quantity,
                'reason_type' => $validated['reason_type'],
                'user_id' => Auth::id(),
                'item_type' => $validated['item_type'],
                'unit' => $unit,
                'unit_cost' => $unitCost,
                'total_cost' => $unitCost * $quantity,
            ]);
        });

        return redirect()->back()->with('success', 'Đã lập phiếu tiêu hủy hàng hỏng thành công.');
    }

    public function storeStocktake(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'session_name' => 'required|string|max:255',
            'items' => 'required|array',
            'items.*' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($validated): void {
            $session = StocktakeSession::query()->create([
                'stocktake_date' => now(),
                'session_name' => $validated['session_name'],
                'status' => 'Pending_Approval',
                'created_by' => Auth::id(),
                'note' => $validated['note'] ?? 'Kiểm kê cuối ca làm việc',
            ]);

            foreach ($validated['items'] as $ingredientId => $actualQty) {
                if ($actualQty === null || $actualQty === '') {
                    continue;
                }

                $ingredient = Ingredient::query()->findOrFail($ingredientId);
                $systemQty = (float) $ingredient->stock_quantity;
                $actualQty = (float) $actualQty;
                $varianceQty = $actualQty - $systemQty;
                $tolerance = (float) ($ingredient->tolerance_rate ?? 5);
                $variancePercent = $systemQty > 0 ? abs($varianceQty / $systemQty) * 100 : 0;
                $reasonCode = $variancePercent > $tolerance ? 'OVER_TOLERANCE' : 'NORMAL';

                StocktakeDetail::query()->create([
                    'stocktake_id' => $session->stocktake_id,
                    'item_type' => 'Ingredient',
                    'ingredient_id' => $ingredient->ingredient_id,
                    'system_qty' => $systemQty,
                    'actual_qty' => $actualQty,
                    'unit' => $ingredient->unit,
                    'variance_qty' => $varianceQty,
                    'variance_percentage' => $variancePercent,
                    'reason_code' => $reasonCode,
                    'explanation' => $reasonCode === 'OVER_TOLERANCE'
                        ? 'Vượt định mức dung sai cho phép, cần giải trình'
                        : 'Trong dung sai',
                    'created_by' => Auth::id(),
                ]);
            }
        });

        return redirect()->back()->with('success', 'Đã hoàn tất phiên kiểm kê kho cuối ngày.');
    }
}
