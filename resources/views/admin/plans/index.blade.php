@extends('layouts.system_layout')

@section('title', 'Lập kế hoạch sản xuất - The Daily Crust')

@section('content')
<div x-data="planBoard(@js([
        'rows' => $rows,
        'stock' => $ingredientStock,
        'locked' => $isPast,
        'approveUrl' => route('admin.plans.approve', '__ID__'),
        'unapproveUrl' => route('admin.plans.unapprove', '__ID__'),
    ]))"
     class="space-y-5">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-mint-900"><i class="fa-solid fa-clipboard-list mr-2 text-mint-700"></i>Lập kế hoạch sản xuất ngày {{ $date->format('d/m/Y') }}</h1>
            <p class="mt-1 text-sm text-stone-500">Lấy dự báo nhu cầu (ML) làm gốc, trừ tồn thành phẩm, kiểm tra nguyên liệu theo BOM rồi duyệt để bếp nhận kế hoạch.</p>
        </div>
        <form method="GET" class="flex flex-wrap items-center gap-2">
            @foreach ($quickDates as $d)
                <a href="{{ route('admin.plans.index', ['date' => $d->toDateString()]) }}"
                   class="rounded-full border px-3 py-1 text-xs font-semibold {{ $d->isSameDay($date) ? 'border-mint-700 bg-mint-700 text-white' : 'border-mint-700/30 bg-[#d8e8d2] text-mint-800 hover:bg-[#c9ddc2]' }}">
                    {{ $d->isToday() ? 'Hôm nay' : ($d->isTomorrow() ? 'Ngày mai' : $d->format('d/m')) }}
                </a>
            @endforeach
            <input type="date" name="date" value="{{ $date->toDateString() }}" onchange="this.form.submit()"
                   class="rounded-lg border border-stone-300 bg-white px-3 py-1.5 text-sm focus:border-mint-700 focus:outline-none">
        </form>
    </div>

    @include('staff.partials.flash')

    {{-- Nguồn dự báo --}}
    @if ($meta)
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-900">
            <span><i class="fa-solid fa-chart-line mr-1"></i><b>Có dự báo</b> cho {{ $meta['count'] }} sản phẩm</span>
            <span>Mô hình: <b>{{ $meta['model'] ?? '—' }}</b></span>
            <span>Tạo lúc: <b>{{ $meta['created_at']?->format('H:i d/m/Y') ?? '—' }}</b></span>
            @if (\Illuminate\Support\Facades\Route::has('admin.forecast.index'))
                <a href="{{ route('admin.forecast.index', ['date' => $date->toDateString()]) }}" class="ml-auto font-semibold underline">Xem chi tiết dự báo</a>
            @endif
        </div>
    @else
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900">
            <i class="fa-solid fa-triangle-exclamation mr-1"></i>Chưa có dự báo cho ngày này (script Python chạy lúc 21:00 hằng ngày). Bạn vẫn có thể nhập sản lượng thủ công.
            @if (\Illuminate\Support\Facades\Route::has('admin.forecast.index'))
                <a href="{{ route('admin.forecast.index') }}" class="font-semibold underline">Mở trang Dự báo nhu cầu</a>
            @endif
        </div>
    @endif

    @if ($isPast)
        <div class="rounded-lg border border-stone-200 bg-stone-100 px-4 py-2.5 text-sm text-stone-600"><i class="fa-solid fa-lock mr-1"></i>Ngày đã qua: chỉ xem, không sửa được kế hoạch.</div>
    @endif

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
        {{-- BẢNG KẾ HOẠCH --}}
        <div class="space-y-3 xl:col-span-2">
            <form id="planForm" method="POST" action="{{ route('admin.plans.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">

                <div class="flex flex-wrap items-center gap-2" x-show="!locked">
                    <button type="button" @click="fillForecast()" class="rounded-lg border border-mint-700 px-3 py-1.5 text-xs font-semibold text-mint-800 hover:bg-[#d8e8d2]"><i class="fa-solid fa-wand-magic-sparkles mr-1"></i>Điền theo dự báo</button>
                    <button type="button" @click="fillSuggested()" class="rounded-lg border border-mint-700 px-3 py-1.5 text-xs font-semibold text-mint-800 hover:bg-[#d8e8d2]"><i class="fa-solid fa-boxes-stacked mr-1"></i>Điền theo gợi ý (đã trừ tồn)</button>
                    <button type="button" @click="clearAll()" class="rounded-lg border border-stone-300 px-3 py-1.5 text-xs font-semibold text-stone-600 hover:bg-stone-50">Xóa trắng</button>
                    <span class="ml-auto text-xs text-stone-500">Tổng kế hoạch: <b class="text-mint-800" x-text="totalQty.toLocaleString('vi-VN')"></b> sản phẩm</span>
                </div>

                <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-[#d8e8d2] text-left text-xs font-bold uppercase tracking-wider text-mint-900">
                                <tr>
                                    <th class="px-3 py-3">Sản phẩm</th>
                                    <th class="px-3 py-3 text-right">Dự báo</th>
                                    <th class="px-3 py-3 text-right">Tồn chuyển sang</th>
                                    <th class="px-3 py-3 text-right">Gợi ý</th>
                                    <th class="px-3 py-3 text-right">Kế hoạch</th>
                                    <th class="px-3 py-3">Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                <template x-for="r in rows" :key="r.product_id">
                                    <tr :class="r.locked ? 'bg-stone-50/70' : ''">
                                        <td class="px-3 py-2.5 align-top">
                                            <span class="block font-bold text-stone-900" x-text="r.name"></span>
                                            <span class="block text-xs text-stone-400" x-text="r.sub"></span>
                                            <a x-show="!r.has_bom" x-cloak href="{{ route('admin.bom.index', ['filter' => 'missing']) }}" class="mt-1 inline-block rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-semibold text-red-800">Chưa có BOM</a>
                                        </td>
                                        <td class="px-3 py-2.5 text-right align-top">
                                            <template x-if="r.forecast !== null">
                                                <div><span class="font-bold text-stone-800" x-text="r.forecast"></span><span class="block text-[11px] text-stone-400" x-text="r.confidence !== null ? 'tin cậy ' + Math.round(r.confidence * 100) + '%' : ''"></span></div>
                                            </template>
                                            <span x-show="r.forecast === null" class="text-stone-300">—</span>
                                        </td>
                                        <td class="px-3 py-2.5 text-right align-top text-stone-600" x-text="r.carry"></td>
                                        <td class="px-3 py-2.5 text-right align-top font-semibold text-mint-800" x-text="r.suggested === null ? '—' : r.suggested"></td>
                                        <td class="px-3 py-2.5 text-right align-top">
                                            <template x-if="!r.locked && !locked">
                                                <div>
                                                    <input type="number" min="0" max="100000" :name="`rows[${r.product_id}][qty]`" x-model.number="r.input" :disabled="!r.has_bom" placeholder="0"
                                                           class="w-24 rounded-lg border border-stone-300 px-2 py-1.5 text-right font-bold focus:border-mint-700 focus:outline-none disabled:bg-stone-100">
                                                    <input type="text" maxlength="255" :name="`rows[${r.product_id}][reason]`" x-model="r.why" x-show="needsReason(r)" x-cloak placeholder="Lý do điều chỉnh (bắt buộc)"
                                                           class="mt-1.5 block w-full min-w-[170px] rounded-lg border border-amber-300 px-2 py-1 text-xs focus:outline-none">
                                                </div>
                                            </template>
                                            <template x-if="r.locked || locked">
                                                <span class="font-bold text-stone-800" x-text="r.qty ?? '—'"></span>
                                            </template>
                                        </td>
                                        <td class="px-3 py-2.5 align-top">
                                            <div class="flex flex-col items-start gap-1 text-[11px] font-semibold">
                                                <span x-show="r.plan_id === null" class="rounded-full bg-stone-100 px-2 py-0.5 text-stone-500">Chưa lập</span>
                                                <span x-show="r.plan_id !== null && !r.approved" x-cloak class="rounded-full bg-amber-100 px-2 py-0.5 text-amber-800">Nháp - chờ duyệt</span>
                                                <span x-show="r.approved" x-cloak class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-800">Đã duyệt</span>
                                                <span x-show="r.acknowledged" x-cloak class="rounded-full bg-sky-100 px-2 py-0.5 text-sky-800">Bếp đã nhận</span>
                                                <span x-show="r.batches > 0" x-cloak class="rounded-full bg-mint-700/10 px-2 py-0.5 text-mint-800" x-text="r.batches + ' mẻ đã làm'"></span>
                                                <span x-show="r.status && r.plan_id !== null" x-cloak class="font-normal text-stone-400" x-text="statusText(r.status)"></span>
                                                <template x-if="r.plan_id !== null && !r.locked && !locked">
                                                    <div class="mt-1">
                                                        <button type="button" x-show="!r.approved" @click="rowAction('approve', r.plan_id)" class="font-semibold text-mint-700 hover:underline">Duyệt</button>
                                                        <button type="button" x-show="r.approved" x-cloak @click="rowAction('unapprove', r.plan_id)" class="font-semibold text-amber-700 hover:underline">Thu hồi duyệt</button>
                                                    </div>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3" x-show="!locked">
                    <p class="text-xs text-stone-500">Sản lượng 0 hoặc để trống = không sản xuất (xóa kế hoạch nháp nếu có). Kế hoạch bếp đã nhận hoặc đã có mẻ được khóa.</p>
                    <div class="flex gap-2">
                        <button name="action" value="draft" :disabled="!canSubmit" class="rounded-lg border border-mint-700 px-4 py-2 text-sm font-semibold text-mint-800 hover:bg-[#d8e8d2] disabled:cursor-not-allowed disabled:opacity-50">Lưu nháp</button>
                        <button name="action" value="approve" :disabled="!canSubmit" class="rounded-lg bg-mint-700 px-5 py-2 text-sm font-semibold text-white hover:bg-mint-800 disabled:cursor-not-allowed disabled:opacity-50"><i class="fa-solid fa-check mr-1"></i>Lưu &amp; duyệt</button>
                    </div>
                </div>
                <p x-show="!locked && missingReason" x-cloak class="text-right text-xs font-semibold text-amber-700">Sản lượng khác dự báo: hãy nhập lý do điều chỉnh ở các dòng được đánh dấu.</p>
            </form>

            {{-- Form dùng chung cho nút Duyệt / Thu hồi từng dòng (không lồng trong form chính) --}}
            <form id="rowActionForm" method="POST" class="hidden">@csrf</form>
        </div>

        {{-- NGUYÊN LIỆU CẦN THEO BOM --}}
        <aside class="space-y-3">
            <div class="sticky top-24 rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
                <h2 class="font-bold text-mint-900"><i class="fa-solid fa-cubes mr-1 text-mint-700"></i>Nguyên liệu cần (theo BOM)</h2>
                <p class="mb-3 text-xs text-stone-500">Tính tức thời từ sản lượng đang nhập, so với tồn kho hiện tại.</p>

                <div x-show="needs.length === 0" class="py-6 text-center text-sm text-stone-400">Nhập sản lượng để xem nguyên liệu cần dùng.</div>
                <div x-show="shortages.length > 0" x-cloak class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-800">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i>Thiếu <span x-text="shortages.length"></span> loại nguyên liệu. Bếp sẽ không ghi được mẻ nếu không bổ sung tồn kho.
                </div>
                <div class="max-h-[55vh] overflow-y-auto">
                    <table class="w-full text-xs" x-show="needs.length > 0">
                        <thead class="text-left text-[11px] font-bold uppercase text-stone-500"><tr><th class="py-1">Nguyên liệu</th><th class="py-1 text-right">Cần</th><th class="py-1 text-right">Tồn</th></tr></thead>
                        <tbody class="divide-y divide-stone-100">
                            <template x-for="n in needs" :key="n.id">
                                <tr :class="n.short > 0 ? 'bg-red-50' : ''">
                                    <td class="py-1.5 pr-1" x-text="n.name"></td>
                                    <td class="py-1.5 text-right font-semibold" x-text="n.need.toLocaleString('vi-VN') + ' ' + n.unit"></td>
                                    <td class="py-1.5 text-right" :class="n.short > 0 ? 'font-bold text-red-700' : 'text-stone-500'" x-text="n.short > 0 ? '−' + n.short.toLocaleString('vi-VN') : n.stock.toLocaleString('vi-VN')"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection

@push('styles')
<style>[x-cloak]{display:none !important}</style>
@endpush

@push('scripts')
<script>
    function planBoard(cfg) {
        // r.input: ô nhập; ban đầu lấy từ kế hoạch đã có (nếu có)
        const rows = cfg.rows.map(r => ({ ...r, input: r.qty ?? '', why: (r.reason && r.status === 'Adjusted_Manual') ? r.reason : '' }));
        return {
            rows, stock: cfg.stock, locked: cfg.locked,

            statusText(s) {
                return { Original: 'Theo dự báo', Adjusted_Manual: 'Đã điều chỉnh', Adjusted_Shortage: 'Hạ do thiếu nguyên liệu', Manual: 'Lập thủ công' }[s] || s;
            },
            qtyOf(r) { return r.locked || this.locked ? Number(r.qty || 0) : Number(r.input || 0); },
            needsReason(r) { return !r.locked && r.forecast !== null && Number(r.input || 0) > 0 && Number(r.input) !== r.forecast; },

            get totalQty() { return this.rows.reduce((s, r) => s + this.qtyOf(r), 0); },
            get missingReason() { return this.rows.some(r => this.needsReason(r) && !String(r.why || '').trim()); },
            get canSubmit() { return !this.missingReason; },

            get needs() {
                const map = {};
                this.rows.forEach(r => {
                    const q = this.qtyOf(r);
                    if (q <= 0) return;
                    r.bom.forEach(b => {
                        const m = map[b.ingredient_id] ||= { id: b.ingredient_id, name: b.name, unit: b.unit, raw: 0 };
                        m.raw += b.per_unit * q;
                    });
                });
                return Object.values(map).map(m => {
                    const need = Math.ceil(Math.round(m.raw * 10000) / 10000);
                    const stock = this.stock[m.id] ?? 0;
                    return { ...m, need, stock, short: Math.max(0, need - stock) };
                }).sort((a, b) => (b.short > 0) - (a.short > 0) || a.name.localeCompare(b.name, 'vi'));
            },
            get shortages() { return this.needs.filter(n => n.short > 0); },

            fillForecast() { this.rows.forEach(r => { if (!r.locked && r.has_bom && r.forecast !== null) r.input = r.forecast; }); },
            fillSuggested() { this.rows.forEach(r => { if (!r.locked && r.has_bom && r.suggested !== null) { r.input = r.suggested; if (r.suggested !== r.forecast) r.why = 'Đã trừ tồn thành phẩm còn hạn chuyển sang'; } }); },
            clearAll() { this.rows.forEach(r => { if (!r.locked) { r.input = ''; r.why = ''; } }); },

            rowAction(kind, id) {
                const f = document.getElementById('rowActionForm');
                f.action = (kind === 'approve' ? cfg.approveUrl : cfg.unapproveUrl).replace('__ID__', id);
                f.submit();
            },
        };
    }
</script>
@endpush
