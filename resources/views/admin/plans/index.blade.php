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
 
    {{-- Tiêu đề + chọn ngày --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-mint-900"><i class="fa-solid fa-clipboard-list mr-2 text-mint-700"></i>Lập kế hoạch sản xuất</h1>
            <p class="mt-1 text-sm text-stone-500">Lấy dự báo (ML) làm gốc, trừ tồn thành phẩm, kiểm tra nguyên liệu theo BOM rồi duyệt để bếp nhận kế hoạch.</p>
        </div>
        <form method="GET" id="planDateForm" class="flex flex-wrap items-center gap-2">
            @foreach ($quickDates as $d)
                <a href="{{ route('admin.plans.index', ['date' => $d->toDateString()]) }}"
                   class="rounded-full border px-3 py-1.5 text-xs font-semibold transition {{ $d->isSameDay($date) ? 'border-mint-700 bg-mint-700 text-white' : 'border-stone-200 bg-white text-stone-600 hover:border-mint-700/40 hover:text-mint-800' }}">
                    {{ $d->isToday() ? 'Hôm nay' : ($d->isTomorrow() ? 'Ngày mai' : $d->format('d/m')) }}
                </a>
            @endforeach
            <div class="relative">
                <i class="fa-regular fa-calendar pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-stone-400"></i>
                <input type="text" id="planDate" name="date" value="{{ $date->toDateString() }}" readonly>
            </div>
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
            <i class="fa-solid fa-triangle-exclamation mr-1"></i>Chưa có dự báo cho ngày này. Bạn vẫn có thể nhập sản lượng thủ công.
            @if (\Illuminate\Support\Facades\Route::has('admin.forecast.index'))
                <a href="{{ route('admin.forecast.index') }}" class="font-semibold underline">Mở trang Dự báo nhu cầu</a>
            @endif
        </div>
    @endif
 
    @if ($isPast)
        <div class="rounded-lg border border-stone-200 bg-stone-100 px-4 py-2.5 text-sm text-stone-600"><i class="fa-solid fa-lock mr-1"></i>Ngày đã qua: chỉ xem, không sửa được kế hoạch.</div>
    @endif
 
    <div class="grid grid-cols-1 gap-5 2xl:grid-cols-[minmax(0,1fr)_22rem]">
        {{-- ============ BẢNG KẾ HOẠCH ============ --}}
        <div class="min-w-0 space-y-3">
            <form id="planForm" method="POST" action="{{ route('admin.plans.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
 
                {{-- Thanh công cụ --}}
                <div class="flex flex-wrap items-center gap-2" x-show="!locked">
                    <button type="button" @click="fillForecast()" class="inline-flex items-center gap-1.5 rounded-lg border border-mint-700 bg-white px-3 py-1.5 text-xs font-semibold text-mint-800 transition hover:bg-[#eef4ec]"><i class="fa-solid fa-wand-magic-sparkles"></i>Điền theo dự báo</button>
                    <button type="button" @click="fillSuggested()" class="inline-flex items-center gap-1.5 rounded-lg border border-mint-700 bg-white px-3 py-1.5 text-xs font-semibold text-mint-800 transition hover:bg-[#eef4ec]"><i class="fa-solid fa-boxes-stacked"></i>Điền theo gợi ý (đã trừ tồn)</button>
                    <button type="button" @click="clearAll()" class="rounded-lg border border-stone-300 bg-white px-3 py-1.5 text-xs font-semibold text-stone-600 transition hover:bg-stone-50">Xóa trắng</button>
                    <div class="ml-auto flex items-center gap-4 text-xs text-stone-500">
                        <span><b class="tnum text-stone-800" x-text="plannedCount"></b>/<span class="tnum" x-text="rows.length"></span> dòng có kế hoạch</span>
                        <span>Tổng: <b class="tnum text-base text-mint-800" x-text="totalQty.toLocaleString('vi-VN')"></b> sản phẩm</span>
                    </div>
                </div>
 
                {{-- Cảnh báo thiếu nguyên liệu (xem chi tiết ở bảng nguyên liệu bên dưới) --}}
                <div x-show="shortages.length > 0" x-cloak class="flex flex-wrap items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>Thiếu <b x-text="shortages.length"></b> loại nguyên liệu (<span x-text="shortages.slice(0, 3).map(s => s.name).join(', ') + (shortages.length > 3 ? '...' : '')"></span>). Bếp sẽ không ghi được mẻ nếu không bổ sung tồn kho.</span>
                    <a href="#needsPanel" class="ml-auto font-semibold underline">Xem chi tiết</a>
                </div>
 
                <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="bg-mint-700 text-left text-[11px] font-semibold uppercase tracking-wide text-white">
                                    <th class="whitespace-nowrap px-4 py-3">Sản phẩm</th>
                                    <th class="whitespace-nowrap px-3 py-3 text-right">Dự báo</th>
                                    <th class="whitespace-nowrap px-3 py-3 text-right">Tồn chuyển sang</th>
                                    <th class="whitespace-nowrap px-3 py-3 text-right">Gợi ý</th>
                                    <th class="whitespace-nowrap px-3 py-3 text-right">Kế hoạch</th>
                                    <th class="whitespace-nowrap px-4 py-3">Trạng thái</th>
                                </tr>
                            </thead>
 
                            {{-- Mỗi nhóm hàng là một <tbody>: tiêu đề nhóm + các sản phẩm của nhóm --}}
                            <template x-for="g in groups" :key="g.category">
                                <tbody class="border-b border-stone-100 last:border-0">
                                    <tr class="bg-[#eef4ec]/70">
                                        <td colspan="6" class="px-4 py-1.5 text-[11px] font-bold uppercase tracking-wider text-mint-800">
                                            <span x-text="g.category"></span> <span class="ml-1 font-medium normal-case tracking-normal text-stone-400" x-text="'· ' + g.rows.length + ' sản phẩm'"></span>
                                        </td>
                                    </tr>
                                    <template x-for="r in g.rows" :key="r.product_id">
                                        <tr class="border-t border-stone-100 transition hover:bg-stone-50/80" :class="r.locked ? 'bg-stone-50/60' : ''">
                                            {{-- Sản phẩm --}}
                                            <td class="px-4 py-2.5 align-middle">
                                                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                                    <span class="font-semibold text-stone-900" x-text="r.name"></span>
                                                    <span x-show="r.made_to_order" x-cloak class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-semibold text-sky-800" title="Đồ uống pha theo đơn: chỉ dùng để chuẩn bị nguyên liệu">Pha theo đơn</span>
                                                    <a x-show="!r.has_bom" x-cloak href="{{ route('admin.bom.index', ['filter' => 'missing']) }}" class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-800">Chưa có BOM</a>
                                                </div>
                                                <span class="block text-xs text-stone-400" x-text="r.sub"></span>
                                            </td>
                                            {{-- Dự báo + độ tin cậy --}}
                                            <td class="whitespace-nowrap px-3 py-2.5 text-right align-middle">
                                                <template x-if="r.forecast !== null">
                                                    <span class="inline-flex items-center justify-end gap-1.5">
                                                        <span class="tnum font-semibold text-stone-800" x-text="r.forecast"></span>
                                                        <span x-show="r.confidence !== null" class="tnum rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                                              :class="r.confidence >= 0.7 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                                              :title="'Độ tin cậy ước lượng từ kiểm định trên dữ liệu lịch sử'"
                                                              x-text="Math.round(r.confidence * 100) + '%'"></span>
                                                    </span>
                                                </template>
                                                <span x-show="r.forecast === null" class="text-stone-300">—</span>
                                            </td>
                                            <td class="tnum px-3 py-2.5 text-right align-middle" :class="r.carry > 0 ? 'font-medium text-stone-700' : 'text-stone-300'" x-text="r.carry"></td>
                                            <td class="tnum px-3 py-2.5 text-right align-middle font-semibold text-mint-800" x-text="r.suggested === null ? '—' : r.suggested"></td>
                                            {{-- Kế hoạch (ô nhập) --}}
                                            <td class="px-3 py-2.5 text-right align-middle">
                                                <template x-if="!r.locked && !locked">
                                                    <div class="flex flex-col items-end">
                                                        <input type="number" min="0" max="100000" :name="`rows[${r.product_id}][qty]`" x-model.number="r.input" :disabled="!r.has_bom" placeholder="0"
                                                               class="tnum w-20 rounded-lg border border-stone-300 bg-white px-2 py-1.5 text-right font-bold text-stone-900 transition focus:border-mint-700 focus:outline-none focus:ring-2 focus:ring-mint-700/20 disabled:bg-stone-100 disabled:text-stone-400">
                                                        <input type="text" maxlength="255" :name="`rows[${r.product_id}][reason]`" x-model="r.why" x-show="needsReason(r)" x-cloak placeholder="Lý do điều chỉnh (bắt buộc)"
                                                               class="mt-1.5 w-56 rounded-lg border border-amber-300 bg-amber-50/50 px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-amber-300/40">
                                                    </div>
                                                </template>
                                                <template x-if="r.locked || locked">
                                                    <span class="tnum font-bold text-stone-800" x-text="r.qty ?? '—'"></span>
                                                </template>
                                            </td>
                                            {{-- Trạng thái: 1 nhãn chính + thao tác, nằm trên 1 dòng --}}
                                            <td class="whitespace-nowrap px-4 py-2.5 align-middle">
                                                <div class="flex items-center gap-3">
                                                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-semibold" :class="stateOf(r).cls">
                                                        <i class="fa-solid text-[9px]" :class="stateOf(r).icon"></i><span x-text="stateOf(r).label"></span>
                                                    </span>
                                                    <span x-show="r.batches > 0" x-cloak class="tnum text-[11px] text-stone-500" x-text="r.batches + ' mẻ'"></span>
                                                    <template x-if="r.plan_id !== null && !r.locked && !locked">
                                                        <span>
                                                            <button type="button" x-show="!r.approved" @click="rowAction('approve', r.plan_id)" class="text-xs font-semibold text-mint-700 hover:underline">Duyệt</button>
                                                            <button type="button" x-show="r.approved" x-cloak @click="rowAction('unapprove', r.plan_id)" class="text-xs font-semibold text-amber-700 hover:underline">Thu hồi</button>
                                                        </span>
                                                    </template>
                                                </div>
                                                <span x-show="r.status && r.plan_id !== null" x-cloak class="mt-0.5 block text-[11px] text-stone-400" x-text="statusText(r.status)"></span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </template>
                        </table>
                    </div>
                </div>
 
                <div class="flex flex-wrap items-center justify-between gap-3" x-show="!locked">
                    <p class="text-xs text-stone-500">Sản lượng 0 hoặc để trống = không sản xuất (xóa kế hoạch nháp nếu có). Kế hoạch bếp đã nhận hoặc đã có mẻ bị khóa.</p>
                    <div class="flex gap-2">
                        <button name="action" value="draft" :disabled="!canSubmit" class="rounded-lg border border-mint-700 bg-white px-4 py-2 text-sm font-semibold text-mint-800 transition hover:bg-[#eef4ec] disabled:cursor-not-allowed disabled:opacity-50">Lưu nháp</button>
                        <button name="action" value="approve" :disabled="!canSubmit" class="rounded-lg bg-mint-700 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-mint-800 disabled:cursor-not-allowed disabled:opacity-50"><i class="fa-solid fa-check mr-1"></i>Lưu &amp; duyệt</button>
                    </div>
                </div>
                <p x-show="!locked && missingReason" x-cloak class="text-right text-xs font-semibold text-amber-700">Sản lượng khác dự báo: hãy nhập lý do điều chỉnh ở các dòng được đánh dấu.</p>
            </form>
 
            {{-- Form dùng chung cho nút Duyệt / Thu hồi từng dòng (không lồng trong form chính) --}}
            <form id="rowActionForm" method="POST" class="hidden">@csrf</form>
        </div>
 
        {{-- ============ NGUYÊN LIỆU CẦN THEO BOM ============ --}}
        <aside id="needsPanel" class="min-w-0">
            <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm 2xl:sticky 2xl:top-24">
                <h2 class="font-bold text-mint-900"><i class="fa-solid fa-cubes mr-1.5 text-mint-700"></i>Nguyên liệu cần (theo BOM)</h2>
                <p class="mb-3 text-xs text-stone-500">Tính tức thời từ sản lượng đang nhập, so với tồn kho hiện tại.</p>
 
                <div x-show="needs.length === 0" class="py-6 text-center text-sm text-stone-400">Nhập sản lượng để xem nguyên liệu cần dùng.</div>
                <div x-show="needs.length > 0" class="max-h-[60vh] overflow-y-auto rounded-lg border border-stone-100">
                    <table class="w-full text-xs">
                        <thead class="sticky top-0 bg-stone-50 text-left text-[11px] font-semibold uppercase tracking-wide text-stone-500"><tr><th class="px-3 py-2">Nguyên liệu</th><th class="whitespace-nowrap px-3 py-2 text-right">Cần</th><th class="whitespace-nowrap px-3 py-2 text-right">Tồn</th></tr></thead>
                        <tbody class="divide-y divide-stone-100">
                            <template x-for="n in needs" :key="n.id">
                                <tr :class="n.short > 0 ? 'bg-red-50' : ''">
                                    <td class="px-3 py-1.5" x-text="n.name"></td>
                                    <td class="tnum whitespace-nowrap px-3 py-1.5 text-right font-semibold" x-text="n.need.toLocaleString('vi-VN') + ' ' + n.unit"></td>
                                    <td class="tnum whitespace-nowrap px-3 py-1.5 text-right" :class="n.short > 0 ? 'font-bold text-red-700' : 'text-stone-500'" x-text="n.short > 0 ? '−' + n.short.toLocaleString('vi-VN') : n.stock.toLocaleString('vi-VN')"></td>
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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<style>
    .flatpickr-day.selected { background: #3f6b3a; border-color: #3f6b3a; }
</style>
@endpush
 
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/vn.js"></script>
<script>
    flatpickr('#planDate', {
        dateFormat: 'Y-m-d', altInput: true, altFormat: 'd/m/Y',
        altInputClass: 'w-36 cursor-pointer rounded-lg border border-stone-300 bg-white py-1.5 pl-9 pr-3 text-sm font-semibold text-stone-800 focus:border-mint-700 focus:outline-none',
        defaultDate: @json($date->toDateString()), disableMobile: true,
        locale: Object.assign({}, (window.flatpickr && flatpickr.l10ns && flatpickr.l10ns.vn) || {}, { firstDayOfWeek: 1 }),
        onChange: (sel, str) => { if (str) document.getElementById('planDateForm').submit(); },
    });
 
    function planBoard(cfg) {
        // r.input: ô nhập; ban đầu lấy từ kế hoạch đã có (nếu có)
        const rows = cfg.rows.map(r => ({ ...r, input: r.qty ?? '', why: (r.reason && r.status === 'Adjusted_Manual') ? r.reason : '' }));
        return {
            rows, stock: cfg.stock, locked: cfg.locked,
 
            statusText(s) {
                return { Original: 'Theo dự báo', Adjusted_Manual: 'Đã điều chỉnh', Adjusted_Shortage: 'Hạ do thiếu nguyên liệu', Manual: 'Lập thủ công' }[s] || s;
            },
            // Một nhãn trạng thái chính cho mỗi dòng
            stateOf(r) {
                if (r.acknowledged) return { label: 'Bếp đã nhận', cls: 'bg-sky-100 text-sky-800', icon: 'fa-clipboard-check' };
                if (r.approved) return { label: 'Đã duyệt', cls: 'bg-emerald-100 text-emerald-800', icon: 'fa-check' };
                if (r.plan_id !== null) return { label: 'Nháp', cls: 'bg-amber-100 text-amber-800', icon: 'fa-pen' };
                return { label: 'Chưa lập', cls: 'bg-stone-100 text-stone-500', icon: 'fa-minus' };
            },
            qtyOf(r) { return r.locked || this.locked ? Number(r.qty || 0) : Number(r.input || 0); },
            needsReason(r) { return !r.locked && r.forecast !== null && Number(r.input || 0) > 0 && Number(r.input) !== r.forecast; },
 
            // Gom theo nhóm hàng (giữ thứ tự xuất hiện)
            get groups() {
                const m = new Map();
                this.rows.forEach(r => { const k = r.category || 'Khác'; if (!m.has(k)) m.set(k, []); m.get(k).push(r); });
                return [...m].map(([category, rs]) => ({ category, rows: rs }));
            },
            get plannedCount() { return this.rows.filter(r => this.qtyOf(r) > 0).length; },
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