<?php

namespace App\Services;

use App\Models\BillOfMaterial;
use App\Models\Ingredient;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Quản lý công thức định lượng (BOM). Chỉ dùng Eloquent; mỗi lần lưu là một transaction. */
class BomService
{
    /**
     * Ghi đè toàn bộ BOM của một sản phẩm theo danh sách dòng gửi lên:
     *  - dòng đã có (cùng nguyên liệu) => cập nhật định lượng,
     *  - dòng mới                      => thêm,
     *  - dòng không còn trong danh sách => xóa.
     *
     * @param  array<int, array{ingredient_id:int|string, quantity:int|float|string}>  $lines
     * @return array{added:int, updated:int, removed:int}
     */
    public function sync(Product $product, array $lines): array
    {
        $clean = [];
        foreach ($lines as $line) {
            $ingredientId = (int) ($line['ingredient_id'] ?? 0);
            $qty = round((float) ($line['quantity'] ?? 0), 2);
            if ($ingredientId <= 0) {
                continue;
            }
            if ($qty <= 0) {
                throw ValidationException::withMessages(['lines' => 'Định lượng của mọi nguyên liệu phải lớn hơn 0.']);
            }
            if (isset($clean[$ingredientId])) {
                throw ValidationException::withMessages(['lines' => 'Mỗi nguyên liệu chỉ được xuất hiện một lần trong công thức.']);
            }
            $clean[$ingredientId] = $qty;
        }

        if ($clean === []) {
            throw ValidationException::withMessages(['lines' => 'Công thức phải có ít nhất một nguyên liệu.']);
        }

        return DB::transaction(function () use ($product, $clean) {
            $product = Product::lockForUpdate()->findOrFail($product->product_id);

            $ingredients = Ingredient::whereIn('ingredient_id', array_keys($clean))->get()->keyBy('ingredient_id');
            if ($ingredients->count() !== count($clean)) {
                throw ValidationException::withMessages(['lines' => 'Có nguyên liệu không tồn tại trong kho.']);
            }

            $existing = BillOfMaterial::where('product_id', $product->product_id)->lockForUpdate()->get()->keyBy('ingredient_id');
            $stats = ['added' => 0, 'updated' => 0, 'removed' => 0];

            foreach ($existing as $ingredientId => $bom) {
                if (! array_key_exists($ingredientId, $clean)) {
                    $bom->delete();
                    $stats['removed']++;
                }
            }

            foreach ($clean as $ingredientId => $qty) {
                $ing = $ingredients[$ingredientId];
                $payload = [
                    'product_name' => $product->vi_name,
                    'ingredient_name' => $ing->ingredient_name,
                    'quantity_required' => $qty,
                    'unit' => $ing->unit,
                ];

                if ($bom = $existing->get($ingredientId)) {
                    $bom->update($payload);
                    $stats['updated']++;
                } else {
                    BillOfMaterial::create($payload + ['product_id' => $product->product_id, 'ingredient_id' => $ingredientId]);
                    $stats['added']++;
                }
            }

            return $stats;
        });
    }

    /**
     * Số sản phẩm tối đa làm được với tồn kho hiện tại và nguyên liệu "nút cổ chai".
     *
     * @return array{max:?int, limiting:?string}
     */
    public function capacity(Product $product): array
    {
        $product->loadMissing('boms.ingredient');

        $max = null;
        $limiting = null;
        foreach ($product->boms as $bom) {
            if ($bom->quantity_required <= 0) {
                continue;
            }
            $can = (int) floor(((int) ($bom->ingredient->stock_quantity ?? 0)) / $bom->quantity_required);
            if ($max === null || $can < $max) {
                $max = $can;
                $limiting = $bom->ingredient->ingredient_name ?? null;
            }
        }

        return ['max' => $max, 'limiting' => $limiting];
    }
}
