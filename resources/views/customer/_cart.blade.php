{{-- Giỏ hàng khách LƯU TRONG CSDL theo tài khoản (đồng bộ mọi trình duyệt/thiết bị) + form đặt hàng online.
     Được include trong dashboard và trang chi tiết sản phẩm cho khách đã đăng nhập. --}}
@php
    $cartInitial = auth()->check() ? app(\App\Services\CartService::class)->lines(auth()->user()) : [];
@endphp
<div x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }" @open-cart.window="open = true" class="contents">

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
                  x-data="{ delivery: 'In_Store' }" @submit="if ($store.cart.count === 0 || $store.cart.hasProblem) $event.preventDefault()">
                @csrf

                <div class="flex-1 space-y-3 overflow-y-auto px-5 py-4">
                    @if ($errors->any())
                        <ul class="list-disc space-y-0.5 rounded-lg bg-red-50 px-5 py-2 text-sm text-red-700">
                            @foreach ($errors->all() as $e)<li>{{$e }}</li>@endforeach
                        </ul>
                    @endif

                    <div class="space-y-2.5">
                        <template x-for="l in $store.cart.items" :key="l.id">
                            <div class="flex gap-2.5 rounded-lg bg-stone-50 p-2 border border-stone-100 items-center">
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
                                    <p x-show="l.problem" x-cloak class="text-xs font-semibold text-red-600"><i class="fa-solid fa-triangle-exclamation mr-1"></i><span x-text="l.problem"></span></p>
                                    <div class="flex items-center justify-between gap-1 pt-1 border-t border-stone-200/60">
                                        <span class="text-xs text-stone-400 truncate max-w-[110px]" x-text="l.sub"></span>
                                        <div class="flex items-center gap-2">
                                            <div class="flex items-center gap-0.5">
                                                <button type="button" @click="$store.cart.dec(l.id)" :disabled="$store.cart.busy" class="h-6 w-6 rounded border border-stone-300 bg-white text-stone-600 hover:bg-stone-100 flex items-center justify-center text-xs">−</button>
                                                <span class="w-6 text-center text-xs font-bold" x-text="l.qty"></span>
                                                <button type="button" @click="$store.cart.inc(l.id)" :disabled="$store.cart.busy || l.qty >= l.stock" :title="l.qty >= l.stock ? 'Đã đạt số lượng tồn kho' : ''" class="h-6 w-6 rounded border border-stone-300 bg-white text-stone-600 hover:bg-stone-100 flex items-center justify-center text-xs disabled:cursor-not-allowed disabled:opacity-40">+</button>
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
                    <p x-show="$store.cart.hasProblem" x-cloak class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><i class="fa-solid fa-circle-exclamation mr-1"></i>Giỏ có sản phẩm hết hàng hoặc vượt tồn kho. Hãy giảm số lượng hoặc xóa sản phẩm đó để đặt hàng.</p>
                    <button type="submit" :disabled="$store.cart.count === 0 || $store.cart.hasProblem || $store.cart.busy"
                            class="w-full rounded-lg bg-mint-700 py-3 font-bold text-white hover:bg-mint-800 disabled:cursor-not-allowed disabled:opacity-50">
                        <i class="fa-solid fa-paper-plane mr-1"></i>Đặt hàng
                    </button>
                </div>
            </form>
        </aside>
    </div>

    {{-- Thông báo nổi: hết hàng / vượt tồn / lỗi mạng ngay khi bấm thêm vào giỏ --}}
    <div x-show="$store.cart.toast" x-cloak x-transition.opacity
         class="fixed bottom-24 left-1/2 z-[60] w-[92%] max-w-md -translate-x-1/2 rounded-xl px-4 py-3 text-sm font-semibold shadow-xl"
         :class="$store.cart.toast && $store.cart.toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-mint-800 text-white'"
         role="alert">
        <i class="fa-solid mr-2" :class="$store.cart.toast && $store.cart.toast.type === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-check'"></i>
        <span x-text="$store.cart.toast ? $store.cart.toast.text : ''"></span>
    </div>
</div>

@push('styles')
<style>[x-cloak]{display:none !important}</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        const TOKEN = @js(csrf_token());
        const URLS = {
            list: @js(route('cart.index')),
            add: @js(route('cart.add')),
            item: (id) => @js(url('/cart/items')) + '/' + id,
            clear: @js(route('cart.clear')),
            merge: @js(route('cart.merge')),
            login: @js(route('login')),
        };
        const LEGACY_KEY = 'tdc_cart_v1';   // giỏ cũ lưu ở trình duyệt (trước khi chuyển sang CSDL)

        Alpine.store('cart', {
            items: @js($cartInitial),
            busy: false,
            toast: null,
            _t: null,

            init() { this.importLegacy(); },

            get count() { return this.items.reduce((s, l) => s + l.qty, 0); },
            get total() { return this.items.reduce((s, l) => s + l.price * l.qty, 0); },
            get hasProblem() { return this.items.some(l => l.problem); },
            fmt(n) { return new Intl.NumberFormat('vi-VN').format(n) + ' đ'; },

            notify(text, type = 'error') {
                this.toast = { text, type };
                clearTimeout(this._t);
                this._t = setTimeout(() => { this.toast = null; }, 4000);
            },

            // Gọi máy chủ. Luôn đồng bộ lại items từ phản hồi (kể cả khi lỗi) để tồn kho hiển thị luôn mới.
            async call(method, url, body) {
                this.busy = true;
                try {
                    const res = await fetch(url, {
                        method,
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json', 'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': TOKEN, 'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: body ? JSON.stringify(body) : undefined,
                    });

                    if (res.redirected || res.status === 401 || res.status === 419) {
                        this.notify('Phiên đăng nhập đã hết hạn, vui lòng đăng nhập lại.');
                        setTimeout(() => { window.location.href = URLS.login; }, 1500);
                        return null;
                    }

                    let data = {};
                    try { data = await res.json(); } catch (e) {}
                    if (Array.isArray(data.items)) this.items = data.items;

                    if (!res.ok) {
                        const first = data.errors ? Object.values(data.errors).flat()[0] : null;
                        this.notify(data.message || first || 'Không thể cập nhật giỏ hàng.');
                        return null;
                    }
                    return data;
                } catch (e) {
                    this.notify('Lỗi kết nối máy chủ, vui lòng thử lại.');
                    return null;
                } finally {
                    this.busy = false;
                }
            },

            // Thêm vào giỏ: chặn NGAY ở trình duyệt nếu đã biết hết hàng / vượt tồn, máy chủ kiểm tra lại lần nữa.
            async add(p) {
                const line = this.items.find(x => x.id === p.id);
                const inCart = line ? line.qty : 0;
                const known = (typeof p.stock === 'number') ? p.stock : (line ? line.stock : null);

                if (known !== null && known <= 0) {
                    this.notify(p.name + ' đã hết hàng, không thể thêm vào giỏ.');
                    return;
                }
                if (known !== null && inCart + 1 > known) {
                    this.notify(p.name + ': chỉ còn ' + known + ' sản phẩm' + (inCart ? ' (trong giỏ bạn đã có ' + inCart + ')' : '') + '.');
                    return;
                }

                const data = await this.call('POST', URLS.add, { product_id: p.id, qty: 1 });
                if (data) this.notify('Đã thêm "' + p.name + '" vào giỏ hàng.', 'ok');
            },

            inc(id) {
                const l = this.items.find(x => x.id === id);
                if (!l) return;
                if (l.qty + 1 > l.stock) {
                    this.notify(l.name + ': chỉ còn ' + l.stock + ' sản phẩm.');
                    return;
                }
                return this.call('PUT', URLS.item(id), { qty: l.qty + 1 });
            },
            dec(id) {
                const l = this.items.find(x => x.id === id);
                if (!l) return;
                return this.call('PUT', URLS.item(id), { qty: l.qty - 1 });   // về 0 thì máy chủ xóa dòng
            },
            remove(id) { return this.call('DELETE', URLS.item(id)); },
            clear() { return this.call('DELETE', URLS.clear); },

            // Giỏ cũ ở LocalStorage -> chuyển lên tài khoản một lần, rồi xóa khóa cũ.
            async importLegacy() {
                let old = [];
                try { old = JSON.parse(localStorage.getItem(LEGACY_KEY)) || []; } catch (e) { old = []; }
                if (!Array.isArray(old) || old.length === 0) {
                    try { localStorage.removeItem(LEGACY_KEY); } catch (e) {}
                    return;
                }
                const data = await this.call('POST', URLS.merge, {
                    items: old.map(l => ({ product_id: l.id, qty: l.qty })),
                });
                if (data) { try { localStorage.removeItem(LEGACY_KEY); } catch (e) {} }
            },
        });
    });
</script>
@endpush
