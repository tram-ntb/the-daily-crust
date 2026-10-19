@extends('layouts.system_layout')

@section('title', 'Bán hàng POS - The Daily Crust')

@section('content')
<div x-data="posApp(@js([
        'products' => $products,
        'url' => route('admin.pos.checkout'),
        'token' => csrf_token(),
    ]))"
     x-init="init()"
     class="space-y-4">

    {{-- Thanh tiêu đề --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-mint-900"><i class="fa-solid fa-cash-register mr-2 text-mint-700"></i>Bán hàng tại quầy</h1>
            <p class="text-sm text-stone-500">Thu ngân: <b class="text-mint-800">{{ $cashier }}</b></p>
        </div>
        <div class="relative w-full max-w-md">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-stone-400"></i>
            <input type="search" x-model="search" placeholder="Tìm sản phẩm..." autofocus
                   class="w-full rounded-lg border border-stone-300 bg-white py-2.5 pl-10 pr-3 text-sm focus:border-mint-700 focus:outline-none">
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">

        {{-- Cột bên trái chứa Danh mục (thanh ngang) và Lưới sản phẩm --}}
        <div class="space-y-4 lg:col-span-8">
            {{-- Thanh danh mục ngang (nằm trên cột lưới sản phẩm) --}}
            <aside class="rounded-xl border border-stone-200 bg-white p-3 shadow-sm">
                <h2 class="mb-2 text-xs font-bold uppercase tracking-wider text-mint-900"><i class="fa-solid fa-utensils mr-1 text-mint-700"></i>Danh mục</h2>
                <div class="flex flex-wrap gap-2">
                    <button @click="cats = []" class="rounded-lg px-3 py-1.5 text-xs font-semibold" :class="cats.length === 0 ? 'bg-mint-700 text-white' : 'text-stone-700 bg-stone-100 hover:bg-[#d8e8d2]'">Tất cả</button>
                    @foreach ($categories as $name =>$count)
                        <button @click="toggleCat(@js($name))"
                                class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold"
                                :class="cats.includes(@js($name)) ? 'bg-mint-700 text-white' : 'text-stone-700 bg-stone-100 hover:bg-[#d8e8d2]'">
                            <span>{{ $name }}</span><span class="rounded-full bg-white/20 px-1.5 py-0.2 text-[10px] opacity-80">{{ $count }}</span>
                        </button>
                    @endforeach
                </div>
            </aside>

            {{-- Lưới sản phẩm --}}
            <section>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <template x-for="p in filtered" :key="p.id">
                        <button type="button" @click="add(p)" :disabled="p.stock <= 0"
                                class="flex gap-3 rounded-xl border border-stone-200 bg-white p-2.5 text-left shadow-sm transition hover:border-mint-700 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50">
                            <div class="flex h-16 w-16 flex-none items-center justify-center overflow-hidden rounded-lg bg-stone-100">
                                <template x-if="p.image"><img :src="p.image" :alt="p.name" class="h-full w-full object-cover"></template>
                                <template x-if="!p.image"><i class="fa-solid fa-bread-slice text-2xl text-stone-300"></i></template>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <span class="text-sm font-bold leading-tight text-stone-900" x-text="p.name"></span>
                                    <span class="whitespace-nowrap text-sm font-bold text-mint-700" x-text="fmt(p.price)"></span>
                                </div>
                                <p class="mt-0.5 truncate text-xs text-stone-400" x-text="p.sub"></p>
                                <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                      :class="p.stock <= 0 ? 'bg-red-100 text-red-700' : (p.stock <= 5 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-50 text-emerald-700')"
                                      x-text="p.stock <= 0 ? 'Hết hàng' : 'Còn ' + p.stock"></span>
                            </div>
                        </button>
                    </template>
                </div>
                <p x-show="filtered.length === 0" x-cloak class="rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center text-stone-400">Không có sản phẩm phù hợp.</p>
            </section>
        </div>

        {{-- Cột bên phải: giỏ hàng --}}
        <aside class="lg:col-span-4">
            <div class="sticky top-24 space-y-3 rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="font-bold text-mint-900"><i class="fa-solid fa-basket-shopping mr-1 text-mint-700"></i>Giỏ hàng <span class="text-sm font-normal text-stone-400" x-text="'(' + count + ' món)'"></span></h2>
                    <button @click="clear()" x-show="count > 0" x-cloak class="text-xs font-semibold text-red-600 hover:underline">Xóa hết</button>
                </div>

                <div class="max-h-[45vh] space-y-2.5 overflow-y-auto pr-1">
                    <template x-for="l in lines" :key="l.id">
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
                                    <span class="text-sm font-bold text-mint-700 whitespace-nowrap" x-text="fmt(l.price)"></span>
                                </div>
                                
                                {{-- Hàng 2: Tên phụ, bộ tăng giảm số lượng và nút xóa (đã xóa giá màu đen) --}}
                                <div class="flex items-center justify-between gap-1 pt-1 border-t border-stone-200/60">
                                    <span class="text-xs text-stone-400 truncate max-w-[110px]" x-text="l.sub"></span>
                                    <div class="flex items-center gap-2">
                                        <div class="flex items-center gap-0.5">
                                            <button @click="dec(l.id)" class="h-6 w-6 rounded border border-stone-300 bg-white text-stone-600 hover:bg-stone-100 flex items-center justify-center text-xs">−</button>
                                            <span class="w-6 text-center text-xs font-bold" x-text="l.qty"></span>
                                            <button @click="inc(l.id)" class="h-6 w-6 rounded border border-stone-300 bg-white text-stone-600 hover:bg-stone-100 flex items-center justify-center text-xs">+</button>
                                        </div>
                                        <button @click="remove(l.id)" class="text-stone-400 hover:text-red-600 px-1"><i class="fa-solid fa-trash-can text-xs"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                    <p x-show="count === 0" class="py-8 text-center text-sm text-stone-400">Chọn sản phẩm bên trái để thêm vào giỏ.</p>
                </div>

                <div class="flex items-baseline justify-between border-t border-stone-200 pt-3">
                    <span class="font-semibold text-stone-600">Tổng tiền</span>
                    <span class="text-2xl font-extrabold text-mint-800" x-text="fmt(total)"></span>
                </div>

                {{-- Phương thức thanh toán (tượng trưng) --}}
                <div>
                    <p class="mb-1 text-xs font-bold uppercase tracking-wider text-stone-500">Phương thức thanh toán</p>
                    <div class="grid grid-cols-3 gap-2">
                        <template x-for="m in methods" :key="m.key">
                            <button @click="payment = m.key" class="rounded-lg border px-2 py-2 text-sm font-semibold"
                                    :class="payment === m.key ? 'border-mint-700 bg-mint-700 text-white' : 'border-stone-300 bg-white text-stone-700 hover:bg-stone-50'">
                                <i class="fa-solid mr-1" :class="m.icon"></i><span x-text="m.label"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div x-show="payment === 'Cash'" x-cloak class="space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-stone-500">Tiền khách đưa</label>
                    <input type="number" min="0" step="1000" x-model="cash" placeholder="0"
                           class="w-full rounded-lg border border-stone-300 px-3 py-2 text-right text-lg font-bold focus:border-mint-700 focus:outline-none">
                    <div class="flex flex-wrap gap-1.5">
                        <button @click="cash = total" class="rounded-md bg-[#d8e8d2] px-2.5 py-1 text-xs font-semibold text-mint-800">Vừa đủ</button>
                        <template x-for="v in [50000, 100000, 200000, 500000]" :key="v">
                            <button @click="cash = v" class="rounded-md bg-stone-100 px-2.5 py-1 text-xs font-semibold text-stone-700 hover:bg-stone-200" x-text="fmt(v)"></button>
                        </template>
                    </div>
                    <p class="text-sm" x-show="cash !== ''">
                        <span x-show="change >= 0">Tiền thừa trả khách: <b class="text-mint-800" x-text="fmt(change)"></b></span>
                        <span x-show="change < 0" class="font-semibold text-red-600">Còn thiếu <span x-text="fmt(-change)"></span></span>
                    </p>
                </div>

                <template x-if="errors.length">
                    <ul class="list-disc space-y-0.5 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">
                        <template x-for="e in errors" :key="e"><li x-text="e"></li></template>
                    </ul>
                </template>

                <button @click="checkout()" :disabled="!canPay"
                        class="w-full rounded-lg bg-mint-700 py-3 text-base font-bold text-white hover:bg-mint-800 disabled:cursor-not-allowed disabled:opacity-50">
                    <i class="fa-solid" :class="busy ? 'fa-spinner fa-spin' : 'fa-circle-check'"></i>
                    <span x-text="busy ? 'Đang xử lý...' : 'Thanh toán / Hoàn tất đơn hàng'"></span>
                </button>
            </div>
        </aside>
    </div>

    {{-- Hóa đơn sau khi thanh toán --}}
    <div x-show="receipt" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl" @click.outside="newOrder()">
            <div class="text-center">
                <div class="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"><i class="fa-solid fa-check text-xl"></i></div>
                <h3 class="text-lg font-bold text-mint-900">Thanh toán thành công</h3>
                <p class="text-sm text-stone-500">Đơn #<span x-text="receipt?.order_id"></span> · <span x-text="receipt?.time"></span></p>
            </div>
            <ul class="my-4 divide-y divide-stone-100 text-sm">
                <template x-for="l in (receipt?.lines || [])" :key="l.product_id">
                    <li class="flex justify-between py-1.5"><span x-text="l.qty + ' × ' + l.name"></span><span x-text="fmt(l.subtotal)"></span></li>
                </template>
            </ul>
            <div class="flex justify-between border-t border-stone-200 pt-2 font-bold"><span>Tổng cộng</span><span x-text="fmt(receipt?.total || 0)"></span></div>
            <template x-if="receipt && receipt.change !== null">
                <div class="mt-1 flex justify-between text-sm text-stone-600"><span>Khách đưa</span><span x-text="fmt(receipt.cash_received)"></span></div>
            </template>
            <template x-if="receipt && receipt.change !== null">
                <div class="flex justify-between text-sm font-semibold text-mint-800"><span>Tiền thừa</span><span x-text="fmt(receipt.change)"></span></div>
            </template>
            <div class="mt-5 flex gap-2">
                <button onclick="window.print()" class="flex-1 rounded-lg border border-stone-300 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-50"><i class="fa-solid fa-print mr-1"></i>In</button>
                <button @click="newOrder()" class="flex-1 rounded-lg bg-mint-700 py-2 text-sm font-bold text-white hover:bg-mint-800">Đơn mới</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>[x-cloak]{display:none !important}</style>
@endpush

@push('scripts')
<script>
    function posApp(cfg) {
        return {
            products: cfg.products, search: '', cats: [], cart: {}, payment: 'Cash', cash: '',
            busy: false, errors: [], receipt: null,
            methods: [
                { key: 'Cash', label: 'Tiền mặt', icon: 'fa-money-bill-wave' },
                { key: 'QR_Code', label: 'QR', icon: 'fa-qrcode' },
                { key: 'Momo', label: 'Momo', icon: 'fa-wallet' },
            ],
            init() { this.byId = Object.fromEntries(this.products.map(p => [p.id, p])); },

            get filtered() {
                const q = this.search.trim().toLowerCase();
                return this.products.filter(p =>
                    (this.cats.length === 0 || this.cats.includes(p.category)) &&
                    (q === '' || (p.name + ' ' + (p.sub || '')).toLowerCase().includes(q)));
            },
            get lines() {
                return Object.entries(this.cart).map(([id, qty]) => {
                    const p = this.byId[id];
                    return { id: Number(id), name: p.name, sub: p.sub, price: p.price, image: p.image, qty, subtotal: p.price * qty };
                });
            },
            get count() { return Object.values(this.cart).reduce((a, b) => a + b, 0); },
            get total() { return this.lines.reduce((s, l) => s + l.subtotal, 0); },
            get change() { return this.cash === '' ? null : Number(this.cash) - this.total; },
            get canPay() {
                if (this.busy || this.count === 0) return false;
                return this.payment !== 'Cash' || this.cash === '' || Number(this.cash) >= this.total;
            },

            fmt(n) { return new Intl.NumberFormat('vi-VN').format(n) + ' đ'; },
            toggleCat(c) { this.cats = this.cats.includes(c) ? this.cats.filter(x => x !== c) : [...this.cats, c]; },

            add(p) {
                const cur = this.cart[p.id] || 0;
                if (p.stock <= 0 || cur >= p.stock) { this.errors = [`${p.name}: chỉ còn ${p.stock} sản phẩm.`]; return; }
                this.errors = [];
                this.cart[p.id] = cur + 1;
            },
            inc(id) { this.add(this.byId[id]); },
            dec(id) { if (this.cart[id] > 1) this.cart[id]--; else delete this.cart[id]; },
            remove(id) { delete this.cart[id]; },
            clear() { this.cart = {}; this.cash = ''; this.errors = []; },

            async checkout() {
                if (!this.canPay) return;
                this.busy = true; this.errors = [];
                try {
                    const res = await fetch(cfg.url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.token },
                        body: JSON.stringify({
                            items: this.lines.map(l => ({ product_id: l.id, qty: l.qty })),
                            payment_method: this.payment,
                            cash_received: this.payment === 'Cash' && this.cash !== '' ? Number(this.cash) : null,
                        }),
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        this.errors = data.errors ? Object.values(data.errors).flat() : [data.message || 'Không thể tạo đơn hàng.'];
                        return;
                    }
                    // Trừ tồn hiển thị tại chỗ để không phải tải lại trang
                    this.lines.forEach(l => { this.byId[l.id].stock = Math.max(0, this.byId[l.id].stock - l.qty); });
                    this.receipt = data;
                    this.clear();
                } catch (e) {
                    this.errors = ['Lỗi kết nối máy chủ, vui lòng thử lại.'];
                } finally {
                    this.busy = false;
                }
            },
            newOrder() { this.receipt = null; this.payment = 'Cash'; },
        };
    }
</script>
@endpush