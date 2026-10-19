{{-- Giỏ hàng khách (Alpine store lưu localStorage) + form đặt hàng online. Được include trong dashboard cho khách đã đăng nhập. --}}
<div x-data="{ open: false }" @open-cart.window="open = true" class="contents">

    {{-- Nút giỏ hàng nổi --}}
    <button type="button" @click="open = true"
            class="fixed bottom-6 right-6 z-40 flex h-14 items-center gap-2 rounded-full bg-mint-700 px-5 font-bold text-white shadow-lg hover:bg-mint-800">
        <i class="fa-solid fa-basket-shopping"></i>
        <span>Giỏ hàng</span>
        <span class="flex h-6 min-w-6 items-center justify-center rounded-full bg-amber-500 px-1.5 text-xs" x-text="$store.cart.count"></span>
    </button>

    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex justify-end bg-black/40" @keydown.escape.window="open = false" @click.self="open = false">
        <aside class="flex h-full w-full max-w-md flex-col bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-stone-200 px-5 py-4">
                <h2 class="text-lg font-bold text-mint-900"><i class="fa-solid fa-basket-shopping mr-2 text-mint-700"></i>Giỏ hàng của bạn</h2>
                <button @click="open = false" class="text-stone-400 hover:text-stone-700"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>

            <form method="POST" action="{{ route('orders.store') }}" class="flex min-h-0 flex-1 flex-col"
                  x-data="{ delivery: 'In_Store' }" @submit="if ($store.cart.count === 0) $event.preventDefault()">
                @csrf

                <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    @if ($errors->any())
                        <ul class="list-disc space-y-0.5 rounded-lg bg-red-50 px-5 py-2 text-sm text-red-700">
                            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                        </ul>
                    @endif

                    <div class="space-y-2">
                        <template x-for="(l, i) in $store.cart.items" :key="l.id">
                            <div class="flex items-center gap-2 rounded-lg bg-stone-50 p-2.5">
                                <input type="hidden" :name="`items[${i}][product_id]`" :value="l.id">
                                <input type="hidden" :name="`items[${i}][qty]`" :value="l.qty">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-stone-800" x-text="l.name"></p>
                                    <p class="text-xs text-stone-400" x-text="$store.cart.fmt(l.price)"></p>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button type="button" @click="$store.cart.dec(l.id)" class="h-7 w-7 rounded-md border border-stone-300 bg-white hover:bg-stone-100">−</button>
                                    <span class="w-7 text-center text-sm font-bold" x-text="l.qty"></span>
                                    <button type="button" @click="$store.cart.inc(l.id)" class="h-7 w-7 rounded-md border border-stone-300 bg-white hover:bg-stone-100">+</button>
                                </div>
                                <span class="w-20 text-right text-sm font-bold" x-text="$store.cart.fmt(l.price * l.qty)"></span>
                            </div>
                        </template>
                        <p x-show="$store.cart.count === 0" class="py-10 text-center text-sm text-stone-400">Giỏ hàng đang trống. Bấm biểu tượng giỏ trên sản phẩm để thêm.</p>
                    </div>

                    <div x-show="$store.cart.count > 0" class="space-y-3">
                        <div>
                            <p class="mb-1 text-xs font-bold uppercase tracking-wider text-stone-500">Nhận hàng</p>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="cursor-pointer rounded-lg border px-3 py-2 text-sm font-semibold" :class="delivery === 'In_Store' ? 'border-mint-700 bg-[#d8e8d2] text-mint-900' : 'border-stone-300'">
                                    <input type="radio" name="delivery_method" value="In_Store" x-model="delivery" class="sr-only"><i class="fa-solid fa-store mr-1"></i>Tự đến lấy
                                </label>
                                <label class="cursor-pointer rounded-lg border px-3 py-2 text-sm font-semibold" :class="delivery === 'Delivery_2km' ? 'border-mint-700 bg-[#d8e8d2] text-mint-900' : 'border-stone-300'">
                                    <input type="radio" name="delivery_method" value="Delivery_2km" x-model="delivery" class="sr-only"><i class="fa-solid fa-motorcycle mr-1"></i>Giao tận nơi (≤ 2 km)
                                </label>
                            </div>
                        </div>
                        <input x-show="delivery === 'Delivery_2km'" x-cloak type="text" name="delivery_address" maxlength="255" value="{{ old('delivery_address') }}"
                               placeholder="Địa chỉ giao hàng" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-mint-700 focus:outline-none">
                        <div>
                            <p class="mb-1 text-xs font-bold uppercase tracking-wider text-stone-500">Thanh toán (tượng trưng)</p>
                            <select name="payment_method" class="w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm">
                                <option value="Cash">Tiền mặt khi nhận hàng</option>
                                <option value="QR_Code">Chuyển khoản QR</option>
                                <option value="Momo">Ví Momo</option>
                            </select>
                        </div>
                        <input type="text" name="note" maxlength="255" value="{{ old('note') }}" placeholder="Ghi chú cho tiệm (không bắt buộc)"
                               class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-mint-700 focus:outline-none">
                    </div>
                </div>

                <div class="space-y-3 border-t border-stone-200 px-5 py-4">
                    <div class="flex items-baseline justify-between">
                        <span class="font-semibold text-stone-600">Tổng cộng</span>
                        <span class="text-2xl font-extrabold text-mint-800" x-text="$store.cart.fmt($store.cart.total)"></span>
                    </div>
                    <button type="submit" :disabled="$store.cart.count === 0"
                            class="w-full rounded-lg bg-mint-700 py-3 font-bold text-white hover:bg-mint-800 disabled:cursor-not-allowed disabled:opacity-50">
                        <i class="fa-solid fa-paper-plane mr-1"></i>Đặt hàng
                    </button>
                </div>
            </form>
        </aside>
    </div>
</div>

@push('styles')
<style>[x-cloak]{display:none !important}</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        const KEY = 'tdc_cart_v1';
        const load = () => { try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch (e) { return []; } };

        Alpine.store('cart', {
            items: load(),
            save() { try { localStorage.setItem(KEY, JSON.stringify(this.items)); } catch (e) {} },
            get count() { return this.items.reduce((s, l) => s + l.qty, 0); },
            get total() { return this.items.reduce((s, l) => s + l.price * l.qty, 0); },
            fmt(n) { return new Intl.NumberFormat('vi-VN').format(n) + ' đ'; },
            add(p) {
                const l = this.items.find(x => x.id === p.id);
                if (l) { if (l.qty < 99) l.qty++; } else { this.items.push({ id: p.id, name: p.name, price: p.price, qty: 1 }); }
                this.save();
                window.dispatchEvent(new CustomEvent('open-cart'));
            },
            inc(id) { const l = this.items.find(x => x.id === id); if (l && l.qty < 99) { l.qty++; this.save(); } },
            dec(id) {
                const l = this.items.find(x => x.id === id);
                if (!l) return;
                if (l.qty > 1) l.qty--; else this.items = this.items.filter(x => x.id !== id);
                this.save();
            },
            clear() { this.items = []; this.save(); },
        });
    });
</script>
@endpush
