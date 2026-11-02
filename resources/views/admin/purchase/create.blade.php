@extends('layouts.system_layout')

@section('title', 'Lập phiếu mua nguyên liệu - The Daily Crust')

@section('content')
<div class="space-y-5" x-data="purchaseForm(@js(['rows' => $rows]))">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-mint-900"><i class="fa-solid fa-cart-plus mr-2 text-mint-700"></i>Lập phiếu mua nguyên liệu</h1>
            <p class="mt-1 text-sm text-stone-500">Gợi ý số lượng dựa trên kế hoạch sản xuất ngày <b>{{ $date->format('d/m/Y') }}</b> (theo BOM) và mức tồn tối thiểu. Bạn có thể chỉnh trước khi lập đơn.</p>
        </div>
        <a href="{{ route('admin.purchase.index') }}" class="text-sm font-semibold text-mint-700 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i>Danh sách đơn mua</a>
    </div>

    @include('staff.partials.flash')

    <form method="POST" action="{{ route('admin.purchase.store') }}" class="space-y-4">
        @csrf
        <div class="grid grid-cols-1 gap-4 rounded-xl border border-stone-200 bg-white p-4 shadow-sm md:grid-cols-3">
            <label class="block"><span class="text-sm font-semibold text-stone-700">Nhà cung cấp</span>
                <select name="supplier_id" required class="mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm">
                    <option value="">-- Chọn nhà cung cấp --</option>
                    @foreach ($suppliers as $s)<option value="{{ $s->supplier_id }}" @selected(old('supplier_id') == $s->supplier_id)>{{ $s->company_name }}</option>@endforeach
                </select></label>
            <label class="block"><span class="text-sm font-semibold text-stone-700">Ngày dự kiến giao</span>
                <input type="text" id="poDate" name="expected_delivery_date" value="{{ old('expected_delivery_date') }}" placeholder="Theo thời gian giao của NCC" readonly></label>
            <label class="block"><span class="text-sm font-semibold text-stone-700">Ghi chú</span>
                <input type="text" name="note" maxlength="255" value="{{ old('note') }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm"></label>
        </div>

        <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-stone-200 px-4 py-3">
                <label class="flex items-center gap-2 text-sm text-stone-600"><input type="checkbox" x-model="onlySuggested" class="rounded border-stone-300 text-mint-700"> Chỉ hiện nguyên liệu cần mua</label>
                <div class="text-sm text-stone-500"><b class="tnum text-stone-800" x-text="picked"></b> nguyên liệu · Tạm tính <b class="tnum text-base text-mint-800" x-text="total.toLocaleString('vi-VN') + 'đ'"></b></div>
            </div>
            <div class="max-h-[60vh] overflow-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-[#d8e8d2] text-left text-xs font-bold uppercase tracking-wider text-mint-900">
                        <tr><th class="px-4 py-2.5">Nguyên liệu</th><th class="px-3 py-2.5 text-right">Tồn</th><th class="px-3 py-2.5 text-right">Tối thiểu</th><th class="px-3 py-2.5 text-right">Cần cho KH</th><th class="px-3 py-2.5">Gợi ý</th><th class="px-3 py-2.5 text-right">Đơn giá</th><th class="px-3 py-2.5 text-right">Số lượng mua</th></tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <template x-for="r in rows" :key="r.id">
                            <tr x-show="!onlySuggested || r.reason !== ''" :class="r.reason ? 'bg-amber-50/40' : ''">
                                <td class="px-4 py-2"><span class="font-medium" x-text="r.name"></span> <span class="text-xs text-stone-400" x-text="r.unit"></span></td>
                                <td class="tnum whitespace-nowrap px-3 py-2 text-right" :class="r.stock < r.min ? 'font-bold text-red-700' : ''" x-text="r.stock.toLocaleString('vi-VN')"></td>
                                <td class="tnum whitespace-nowrap px-3 py-2 text-right text-stone-500" x-text="r.min.toLocaleString('vi-VN')"></td>
                                <td class="tnum whitespace-nowrap px-3 py-2 text-right text-stone-500" x-text="r.plan_need ? r.plan_need.toLocaleString('vi-VN') : '—'"></td>
                                <td class="whitespace-nowrap px-3 py-2"><span x-show="r.reason" class="inline-flex rounded-md border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800" x-text="r.reason"></span></td>
                                <td class="tnum whitespace-nowrap px-3 py-2 text-right text-stone-500" x-text="r.price.toLocaleString('vi-VN') + 'đ'"></td>
                                <td class="px-3 py-2 text-right"><input type="number" min="0" :name="`rows[${r.id}][qty]`" x-model.number="r.qty" class="tnum w-28 rounded-md border border-stone-300 px-2 py-1 text-right font-semibold focus:border-mint-700 focus:outline-none"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="flex justify-end"><button class="rounded-lg bg-mint-700 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-mint-800" :disabled="picked === 0" :class="picked === 0 ? 'opacity-50' : ''"><i class="fa-solid fa-paper-plane mr-1"></i>Lập đơn mua</button></div>
    </form>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<style>[x-cloak]{display:none !important} .flatpickr-day.selected{background:#3f6b3a;border-color:#3f6b3a}</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/vn.js"></script>
<script>
    flatpickr('#poDate', { dateFormat: 'Y-m-d', altInput: true, altFormat: 'd/m/Y', minDate: 'today', disableMobile: true,
        altInputClass: 'mt-1 w-full cursor-pointer rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm',
        locale: Object.assign({}, (window.flatpickr && flatpickr.l10ns && flatpickr.l10ns.vn) || {}, { firstDayOfWeek: 1 }) });

    function purchaseForm(cfg) {
        return {
            rows: cfg.rows, onlySuggested: true,
            get picked() { return this.rows.filter(r => Number(r.qty) > 0).length; },
            get total() { return this.rows.reduce((s, r) => s + (Number(r.qty) > 0 ? Number(r.qty) * r.price : 0), 0); },
        };
    }
</script>
@endpush
