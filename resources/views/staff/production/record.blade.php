@extends('layouts.system_layout')

@section('title', 'Ghi nhận mẻ bánh - The Daily Crust')

@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <div>
        <a href="{{ route('admin.production.index', ['date' => $plan->plan_date->toDateString()]) }}" class="text-sm text-mint-700 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i>Quay lại kế hoạch</a>
        <h1 class="mt-1 text-2xl font-bold text-mint-900">Ghi nhận mẻ: {{ $plan->product->vi_name }}</h1>
        <p class="text-sm text-stone-500">Kế hoạch #{{ $plan->plan_id }} · còn tối đa <b class="text-mint-800">{{ $remaining }}</b> sản phẩm đạt được ghi nhận.</p>
    </div>

    @include('staff.partials.flash')

    <form method="POST" action="{{ route('admin.production.batches.store', $plan) }}"
          x-data="batchForm(@js([
              'remaining' => $remaining,
              'bom' => $bom,
              'passed' => (int) old('passed_qty', 0),
              'fails' => old('failures') ? array_values(old('failures')) : [],
              'types' => $failureTypes,
          ]))"
          class="space-y-5 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
        @csrf

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="text-sm font-semibold text-stone-700">Số lượng ĐẠT chuẩn</span>
                <input type="number" name="passed_qty" min="0" x-model.number="passed" required
                       class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-lg font-bold text-emerald-700 focus:border-mint-700 focus:outline-none">
                <span class="text-xs text-stone-400">Nhập kho thành phẩm (tạo lô mới)</span>
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-stone-700">Giờ bắt đầu (tùy chọn)</span>
                <div class="relative mt-1">
                    <i class="fa-regular fa-clock pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-stone-400"></i>
                    <input type="text" id="startTime" name="start_time" value="{{ old('start_time') }}" placeholder="--:--" readonly>
                </div>
                <div class="mt-1 flex items-center justify-between text-xs text-stone-400">
                    <span>Giờ kết thúc = lúc lưu</span>
                    <span class="space-x-2"><button type="button" onclick="window.startTimePicker.setDate(new Date(), true)" class="font-semibold text-mint-700 hover:underline">Bây giờ</button><button type="button" onclick="window.startTimePicker.clear()" class="hover:underline">Xóa</button></span>
                </div>
            </label>
        </div>

        {{-- Bánh hỏng: phân loại theo loại lỗi --}}
        <div class="rounded-lg border border-red-200 bg-red-50/40 p-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-red-800"><i class="fa-solid fa-bug mr-1"></i>Bánh hỏng / lỗi (phân loại)</h2>
                <span class="text-sm">Tổng lỗi: <b class="text-red-700" x-text="failed"></b></span>
            </div>
            <p class="mb-2 text-xs text-stone-500">Mỗi loại lỗi sẽ tự lập một phiếu hủy riêng để Quản lý thống kê nguyên nhân. Không có bánh hỏng thì bỏ trống.</p>

            <div class="space-y-2">
                <template x-for="(f, i) in fails" :key="f.key">
                    <div class="flex items-center gap-2">
                        <select :name="`failures[${i}][type]`" x-model="f.type" class="flex-1 rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm focus:border-mint-700 focus:outline-none">
                            <option value="">-- Chọn loại lỗi --</option>
                            <template x-for="t in types" :key="t"><option :value="t" x-text="t"></option></template>
                        </select>
                        <input type="number" min="1" :name="`failures[${i}][qty]`" x-model.number="f.qty" placeholder="SL"
                               class="w-24 rounded-lg border border-stone-300 px-3 py-2 text-right font-bold text-red-700 focus:border-mint-700 focus:outline-none">
                        <button type="button" @click="removeFail(f.key)" class="px-2 text-stone-400 hover:text-red-600" title="Xóa dòng"><i class="fa-solid fa-trash-can"></i></button>
                    </div>
                </template>
            </div>
            <button type="button" @click="addFail()" class="mt-2 text-sm font-semibold text-mint-700 hover:underline"><i class="fa-solid fa-plus mr-1"></i>Thêm loại lỗi</button>

            <div x-show="failed > 0" x-cloak class="mt-3">
                <label class="block">
                    <span class="text-sm font-semibold text-stone-700">Ghi chú nguyên nhân <span x-show="hasOther" class="text-red-600">* (bắt buộc khi chọn "Khác")</span></span>
                    <input type="text" name="failure_note" maxlength="150" value="{{ old('failure_note') }}" placeholder="VD: lò nóng không đều, bột để quá lâu..."
                           class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-mint-700 focus:outline-none">
                </label>
            </div>
            <p x-show="incompleteFail" x-cloak class="mt-2 text-xs font-semibold text-red-700">Mỗi dòng lỗi cần chọn loại và nhập số lượng lớn hơn 0.</p>
        </div>

        <p x-show="passed > remaining" x-cloak class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">
            Số đạt vượt phần còn lại của kế hoạch (tối đa <span x-text="remaining"></span>).
        </p>

        {{-- Nguyên liệu sẽ bị trừ theo BOM --}}
        <div>
            <h2 class="mb-2 text-sm font-semibold text-mint-900">Nguyên liệu sẽ tự động trừ (đạt + lỗi = <span x-text="passed + failed"></span> sản phẩm)</h2>
            <div class="overflow-hidden rounded-lg border border-stone-200">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 text-left text-xs font-bold uppercase text-stone-500">
                        <tr><th class="px-3 py-2">Nguyên liệu</th><th class="px-3 py-2 text-right">Định mức/1</th><th class="px-3 py-2 text-right">Cần dùng</th><th class="px-3 py-2 text-right">Tồn kho</th></tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <template x-for="r in rows" :key="r.ingredient_id">
                            <tr :class="r.short > 0 ? 'bg-red-50' : ''">
                                <td class="px-3 py-1.5" x-text="r.name"></td>
                                <td class="px-3 py-1.5 text-right text-stone-500" x-text="r.per_unit + ' ' + r.unit"></td>
                                <td class="px-3 py-1.5 text-right font-semibold" x-text="r.needed.toLocaleString('vi-VN')"></td>
                                <td class="px-3 py-1.5 text-right" :class="r.short > 0 ? 'font-bold text-red-700' : 'text-stone-600'"
                                    x-text="r.stock.toLocaleString('vi-VN') + (r.short > 0 ? ' (thiếu ' + r.short.toLocaleString('vi-VN') + ')' : '')"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p x-show="hasShortage" x-cloak class="mt-2 text-sm font-semibold text-red-700">Không đủ nguyên liệu: hệ thống sẽ từ chối lưu mẻ này.</p>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.production.index', ['date' => $plan->plan_date->toDateString()]) }}" class="rounded-lg border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-600 hover:bg-stone-50">Hủy</a>
            <button type="submit" :disabled="!canSave"
                    class="rounded-lg bg-mint-700 px-5 py-2 text-sm font-semibold text-white hover:bg-mint-800 disabled:cursor-not-allowed disabled:opacity-50">
                <i class="fa-solid fa-floppy-disk mr-1"></i>Lưu mẻ bánh
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script>
    // Bộ chọn giờ 24h (không AM/PM), đồng bộ giao diện với lịch chọn ngày
    window.startTimePicker = flatpickr('#startTime', {
        enableTime: true, noCalendar: true, dateFormat: 'H:i', time_24hr: true, minuteIncrement: 5,
        defaultDate: document.getElementById('startTime').value || null, disableMobile: true,
        // class áp dụng cho ô hiển thị
        onReady: (s, d, inst) => { inst.input.className = 'w-full cursor-pointer rounded-lg border border-stone-300 bg-white py-2 pl-9 pr-3 text-sm font-semibold text-stone-800 focus:border-mint-700 focus:outline-none'; },
    });
</script>
<script>
    function batchForm(cfg) {
        let seq = 0;
        const withKey = f => ({ key: ++seq, type: f.type ?? '', qty: Number(f.qty) || '' });
        return {
            remaining: cfg.remaining, passed: cfg.passed, types: cfg.types,
            fails: cfg.fails.map(withKey),

            addFail() { this.fails.push(withKey({})); },
            removeFail(key) { this.fails = this.fails.filter(f => f.key !== key); },

            get failed() { return this.fails.reduce((s, f) => s + (Number(f.qty) > 0 ? Number(f.qty) : 0), 0); },
            get hasOther() { return this.fails.some(f => f.type === 'Khác' && Number(f.qty) > 0); },
            get incompleteFail() { return this.fails.some(f => (f.type === '') !== !(Number(f.qty) > 0)); },

            get rows() {
                const produced = (this.passed || 0) + this.failed;
                return cfg.bom.map(b => {
                    const needed = Math.ceil(Math.round(b.per_unit * produced * 10000) / 10000);
                    return { ...b, needed, short: Math.max(0, needed - b.stock) };
                });
            },
            get hasShortage() { return this.rows.some(r => r.short > 0); },
            get canSave() {
                return (this.passed + this.failed) > 0 && this.passed <= this.remaining && !this.hasShortage && !this.incompleteFail;
            },
        };
    }
</script>
@endpush

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<style>[x-cloak]{display:none !important} .flatpickr-time input:hover,.flatpickr-time .flatpickr-am-pm:hover{background:#eef4ec}</style>
@endpush
