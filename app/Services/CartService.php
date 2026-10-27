<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Giỏ hàng lưu trong CSDL theo tài khoản. Máy chủ là nơi DUY NHẤT quyết định giá và tồn kho:
 *  - thêm / tăng số lượng chỉ thành công khi còn đủ hàng bán được (lô còn hạn),
 *  - mỗi dòng trả về kèm tồn kho hiện tại để giao diện cảnh báo ngay.
 * Chỉ dùng Eloquent.
 */
class CartService
{
    public const MAX_QTY = 99;

    /** Tồn kho BÁN ĐƯỢC (lô còn hàng và chưa hết hạn) của các sản phẩm: [product_id => số lượng]. */
    public function stockFor(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return ProductLot::sellable()
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'remaining_qty'])
            ->groupBy('product_id')
            ->map(fn ($lots) => (int) $lots->sum('remaining_qty'))
            ->all();
    }

    /**
     * Các dòng giỏ hàng để hiển thị (đã có giá, ảnh, tồn kho, cảnh báo).
     *
     * @return array<int, array<string, mixed>>
     */
    public function lines(User $user): array
    {
        $rows = CartItem::query()->where('user_id', $user->user_id)->orderBy('cart_item_id')->get();
        if ($rows->isEmpty()) {
            return [];
        }

        $ids = $rows->pluck('product_id')->all();
        $products = Product::query()->whereIn('product_id', $ids)->get()->keyBy('product_id');
        $stock = $this->stockFor($ids);

        $lines = [];
        foreach ($rows as $row) {
            $p = $products->get($row->product_id);
            if (! $p) {            // sản phẩm đã bị xóa khỏi hệ thống
                $row->delete();

                continue;
            }

            $have = (int) ($stock[$p->product_id] ?? 0);
            $qty = (int) $row->quantity;

            $problem = null;
            if (! $this->isActive($p)) {
                $problem = 'Ngừng bán';
            } elseif ($have <= 0) {
                $problem = 'Đã hết hàng';
            } elseif ($qty > $have) {
                $problem = "Chỉ còn {$have} sản phẩm";
            }

            $lines[] = [
                'id' => (int) $p->product_id,
                'name' => $p->vi_name,
                'sub' => $p->item,
                'price' => (int) $p->price,
                'image' => $p->image_url && file_exists(public_path($p->image_url)) ? asset($p->image_url) : null,
                'qty' => $qty,
                'stock' => $have,
                'problem' => $problem,
            ];
        }

        return $lines;
    }

    /** Thêm $qty sản phẩm vào giỏ (mặc định 1). Ném ValidationException (khóa 'cart') nếu hết hàng / vượt tồn. */
    public function add(User $user, int $productId, int $qty = 1): array
    {
        return DB::transaction(function () use ($user, $productId, $qty) {
            $product = $this->sellableProduct($productId);
            $row = $this->row($user, $productId, true);
            $cur = (int) ($row?->quantity ?? 0);
            $new = $cur + max(1, $qty);

            $this->assertQty($product, $cur, $new, $this->stockFor([$productId])[$productId] ?? 0);
            $this->save($user, $productId, $new, $row);

            return $this->lines($user);
        });
    }

    /** Đặt số lượng một dòng. 0 trở xuống = xóa dòng. Giảm số lượng luôn được phép. */
    public function setQty(User $user, int $productId, int $qty): array
    {
        if ($qty <= 0) {
            return $this->remove($user, $productId);
        }

        return DB::transaction(function () use ($user, $productId, $qty) {
            $product = Product::find($productId);
            if (! $product) {
                return $this->remove($user, $productId);
            }

            $row = $this->row($user, $productId, true);
            $cur = (int) ($row?->quantity ?? 0);

            $this->assertQty($product, $cur, $qty, $this->stockFor([$productId])[$productId] ?? 0);
            $this->save($user, $productId, $qty, $row);

            return $this->lines($user);
        });
    }

    public function remove(User $user, int $productId): array
    {
        CartItem::query()->where('user_id', $user->user_id)->where('product_id', $productId)->delete();

        return $this->lines($user);
    }

    public function clear(User $user): void
    {
        CartItem::query()->where('user_id', $user->user_id)->delete();
    }

    /**
     * Nhập giỏ cũ lưu ở LocalStorage khi khách đăng nhập lần đầu sau nâng cấp.
     * Sản phẩm đã có trong giỏ trên máy chủ thì GIỮ số lượng của máy chủ (chạy lại không bị nhân đôi);
     * số lượng được cắt theo tồn kho, hết hàng thì bỏ qua.
     *
     * @param  array<int, array{product_id:int|string, qty:int|string}>  $items
     */
    public function merge(User $user, array $items): array
    {
        DB::transaction(function () use ($user, $items) {
            foreach ($items as $it) {
                $pid = (int) ($it['product_id'] ?? 0);
                $qty = (int) ($it['qty'] ?? 0);
                if ($pid <= 0 || $qty <= 0 || $this->row($user, $pid, true)) {
                    continue;
                }

                $product = Product::find($pid);
                if (! $product || ! $this->isActive($product)) {
                    continue;
                }

                $take = min($qty, self::MAX_QTY, $this->stockFor([$pid])[$pid] ?? 0);
                if ($take >= 1) {
                    $this->save($user, $pid, $take, null);
                }
            }
        });

        return $this->lines($user);
    }

    /**
     * Danh sách để đặt hàng, lấy từ giỏ trong CSDL (không tin dữ liệu trình duyệt gửi lên).
     *
     * @return array<int, array{product_id:int, qty:int}>
     */
    public function itemsForOrder(User $user): array
    {
        return CartItem::query()->where('user_id', $user->user_id)->orderBy('cart_item_id')->get(['product_id', 'quantity'])
            ->map(fn ($r) => ['product_id' => (int) $r->product_id, 'qty' => (int) $r->quantity])
            ->all();
    }

    // ---------------------------------------------------------------- nội bộ

    private function isActive(Product $p): bool
    {
        return $p->status === null || $p->status === 'Active';
    }

    private function sellableProduct(int $productId): Product
    {
        $product = Product::find($productId);
        if (! $product || ! $this->isActive($product)) {
            throw ValidationException::withMessages(['cart' => 'Sản phẩm này không còn được bán.']);
        }

        return $product;
    }

    private function row(User $user, int $productId, bool $lock = false): ?CartItem
    {
        $q = CartItem::query()->where('user_id', $user->user_id)->where('product_id', $productId);

        return ($lock ? $q->lockForUpdate() : $q)->first();
    }

    /** Chỉ chặn khi số lượng TĂNG vượt tồn; giảm thì luôn cho phép để khách tự sửa giỏ. */
    private function assertQty(Product $product, int $cur, int $new, int $have): void
    {
        if ($new <= $cur) {
            return;
        }

        if ($have <= 0) {
            throw ValidationException::withMessages(['cart' => "{$product->vi_name} đã hết hàng, không thể thêm vào giỏ."]);
        }

        if ($new > $have) {
            $extra = $cur > 0 ? " (trong giỏ bạn đã có {$cur})" : '';

            throw ValidationException::withMessages(['cart' => "{$product->vi_name}: chỉ còn {$have} sản phẩm{$extra}."]);
        }

        if ($new > self::MAX_QTY) {
            throw ValidationException::withMessages(['cart' => 'Mỗi sản phẩm tối đa '.self::MAX_QTY.' trong một đơn.']);
        }
    }

    private function save(User $user, int $productId, int $qty, ?CartItem $row): void
    {
        if ($row) {
            $row->update(['quantity' => $qty]);
        } else {
            CartItem::create(['user_id' => $user->user_id, 'product_id' => $productId, 'quantity' => $qty]);
        }
    }
}
