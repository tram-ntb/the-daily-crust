<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * API giỏ hàng lưu trong CSDL (trả JSON cho Alpine.js). Chỉ khách hàng đã đăng nhập (chặn ở route).
 * Mọi phản hồi, kể cả lỗi, đều kèm `items` mới nhất để giao diện đồng bộ lại ngay.
 */
class CartController extends Controller
{
    public function __construct(private readonly CartService $cart)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->ok($request, $this->cart->lines($request->user()));
    }

    /** Thêm vào giỏ: kiểm tra tồn kho NGAY tại đây, hết hàng hoặc vượt tồn thì trả 422 kèm thông báo. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,product_id'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:'.CartService::MAX_QTY],
        ]);

        return $this->run($request, fn () => $this->cart->add($request->user(), (int) $data['product_id'], (int) ($data['qty'] ?? 1)));
    }

    public function update(Request $request, int $productId): JsonResponse
    {
        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:0', 'max:'.CartService::MAX_QTY],
        ]);

        return $this->run($request, fn () => $this->cart->setQty($request->user(), $productId, (int) $data['qty']));
    }

    public function destroy(Request $request, int $productId): JsonResponse
    {
        return $this->ok($request, $this->cart->remove($request->user(), $productId));
    }

    public function clear(Request $request): JsonResponse
    {
        $this->cart->clear($request->user());

        return $this->ok($request, []);
    }

    /** Nhập giỏ cũ từ LocalStorage (một lần sau khi nâng cấp). */
    public function merge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['nullable', 'array', 'max:60'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:'.CartService::MAX_QTY],
        ]);

        return $this->ok($request, $this->cart->merge($request->user(), $data['items'] ?? []));
    }

    // ---------------------------------------------------------------- nội bộ

    private function run(Request $request, callable $action): JsonResponse
    {
        try {
            return $this->ok($request, $action());
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Không thể cập nhật giỏ hàng.';

            return response()->json([
                'message' => $message,
                'items' => $this->cart->lines($request->user()),   // làm mới tồn kho hiển thị
            ], 422);
        }
    }

    private function ok(Request $request, array $items): JsonResponse
    {
        return response()->json(['items' => $items]);
    }
}
