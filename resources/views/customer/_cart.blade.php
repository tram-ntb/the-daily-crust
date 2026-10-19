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
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-bold text-mint-900"><i class="fa-solid fa-basket-shopping mr-2 text-mint-700"></i>Giỏ hàng <span class="text-sm font-normal text-stone-400" x-text="'(' + $store.cart.count + ' món)'"></span></h2>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="$store.cart.clear()" x-show="$store.cart.count > 0" x-cloak class="text-xs font-semibold text-red-600 hover:underline">Xóa hết</button>
                    <button @click="open = false" class="text-stone-400 hover:text-stone-700"><i class="fa-solid fa-xmark text-xl"></i></button>
                </div>
            </div>

            <form method="POST" action="{{ route('orders.store') }}" class="flex min-h-0 flex-1 flex-col"
                  x-data="{ delivery: 'In_Store' }" @submit="if ($store.cart.count === 0)$event.preventDefault()">
                @csrf

                <div class="flex-1 space-y-3 overflow-y-auto px-5 py-4">
                    @if ($errors->any())
                        <ul class="list-disc space-y-0.5 rounded-lg bg-red-50 px-5 py-2 text-sm text-red-700">
                            @foreach ($errors->all() as $e)<li>{{$e }}</li>@endforeach
                        </ul>
                    @endif

                    <div class="space-y-2.5">
                        <template x-for="(l, i) in $store.cart.items" :key="l.id">
                            <div class="flex gap-2.5 rounded-lg bg-stone-50 p-2 border border-stone-100 items-center">
                                <input type="hidden" :name="`items[${i}][product_id]`" :value="l.id">
                                <input type="hidden" :name="`items[${i}][qty]`" :value="l.qty">

                                {{-- Hình ảnh sản phẩm trong giỏ hàng --}}
                                <div class="flex h-12 w-12 flex-none items-center justify-center overflow-hidden rounded-md bg-stone-200/60 border border-stone-200">
                                    <template x-if="l.image"><img :src="l.image" :alt="l.name" class="h-full w-full object-cover"></template>
                                    <template x-if="!l.image"><i class="fa-solid fa-bread-slice text-lg text-stone-400"></i></template>
                                </div>

                                <div class="min-w-0 flex-1 space-y-1">
                                    {{-- Hàng 1: Tên sản phẩm chính và Đơn giá màu xanh lá --}}
                                    <div class="flex items-start justify-between gap-2">
                                        <span class="text-sm font-bold text-stone-900 leading-tight" x-text="l.name"></span>
                                        <span class="text-sm font-bold text-mint-700 whitespace-nowrap" x-text="$store.cart.fmt(l.price)"></span>
                                    </div>
                                    
                                    {{-- Hàng 2: Tên phụ, bộ tăng giảm số lượng và nút xóa --}}
                                    <div class="flex items-center justify-between gap-1 pt-1 border-t border-stone-200/60">
                                        <span class="text-xs text-stone-400 truncate max-w-[110px]" x-text="l.sub"></span>
                                        <div class="flex items-center gap-2">
                                            <div class="flex items-center gap-0.5">
                                                <button type="button" @click="$store.cart.dec(l.id)" class="h-6 w-6 rounded border border-stone-300 bg-white text-stone-600 hover:bg-stone-100 flex items-center justify-center text-xs">−</button>
                                                <span class="w-6 text-center text-xs font-bold" x-text="l.qty"></span>
                                                <button type="button" @click="$store.cart.inc(l.id)" class="h-6 w-6 rounded border border-stone-300 bg-white text-stone-600 hover:bg-stone-100 flex items-center justify-center text-xs">+</button>
                                            </div>
                                            <button type="button" @click="$store.cart.remove(l.id)" class="text-stone-400 hover:text-red-600 px-1"><i class="fa-solid fa-trash-can text-xs"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <p x-show="$store.cart.count === 0" class="py-10 text-center text-sm text-stone-400">Giỏ hàng đang trống. Bấm biểu tượng giỏ trên sản phẩm để thêm.</p>
                    </div>

                    <div x-show="$store.cart.count > 0" class="space-y-3 pt-2">
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
                if (l) { if (l.qty < 99) l.qty++; } else { this.items.push({ id: p.id, name: p.name, sub: p.sub, price: p.price, image: p.image, qty: 1 }); }
                this.save();
                // Đã gỡ bỏ sự kiện tự động mở giỏ hàng (window.dispatchEvent) tại đây
            },
            inc(id) { const l = this.items.find(x => x.id === id); if (l && l.qty < 99) { l.qty++; this.save(); } },
            dec(id) {
                const l = this.items.find(x => x.id === id);
                if (!l) return;
                if (l.qty > 1) l.qty--; else this.items = this.items.filter(x => x.id !== id);
                this.save();
            },
            remove(id) { this.items = this.items.filter(x => x.id !== id); this.save(); },
            clear() { this.items = []; this.save(); },
        });
    });
</script>
@endpush