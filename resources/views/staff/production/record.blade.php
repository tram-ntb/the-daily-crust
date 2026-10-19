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
          x-data="batchForm({ remaining: {{ $remaining }}, bom: @js($bom), passed: {{ (int) old('passed_qty', 0) }}, failed: {{ (int) old('failed_qty', 0) }} })"
          class="space-y-5 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
        @csrf

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <label class="block">
                <span class="text-sm font-semibold text-stone-700">Số lượng ĐẠT</span>
                <input type="number" name="passed_qty" min="0" x-model.number="passed" required
                       class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-lg font-bold text-emerald-700 focus:border-mint-700 focus:outline-none">
                <span class="text-xs text-stone-400">Nhập kho thành phẩm</span>
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-stone-700">Số lượng LỖI</span>
                <input type="number" name="failed_qty" min="0" x-model.number="failed" required
                       class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-lg font-bold text-red-700 focus:border-mint-700 focus:outline-none">
                <span class="text-xs text-stone-400">Tự lập phiếu hủy</span>
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-stone-700">Giờ bắt đầu (tùy chọn)</span>
                <input type="time" name="start_time" value="{{ old('start_time') }}"
                       class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 focus:border-mint-700 focus:outline-none">
                <span class="text-xs text-stone-400">Giờ kết thúc = lúc lưu</span>
            </label>
        </div>

        <div x-show="failed > 0" x-cloak>
            <label class="block">
                <span class="text-sm font-semibold text-stone-700">Ghi chú nguyên nhân lỗi <span class="text-red-600">*</span></span>
                <input type="text" name="failure_note" maxlength="200" value="{{ old('failure_note') }}" placeholder="VD: cháy đáy, bánh xẹp, nướng chưa chín..."
                       class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 focus:border-mint-700 focus:outline-none">
            </label>
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
            <button type="submit" :disabled="(passed + failed) <= 0 || passed > remaining || hasShortage"
                    class="rounded-lg bg-mint-700 px-5 py-2 text-sm font-semibold text-white hover:bg-mint-800 disabled:cursor-not-allowed disabled:opacity-50">
                <i class="fa-solid fa-floppy-disk mr-1"></i>Lưu mẻ bánh
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function batchForm(cfg) {
        return {
            remaining: cfg.remaining, passed: cfg.passed, failed: cfg.failed,
            get rows() {
                const produced = (this.passed || 0) + (this.failed || 0);
                return cfg.bom.map(b => {
                    const needed = Math.ceil(Math.round(b.per_unit * produced * 10000) / 10000);
                    return { ...b, needed, short: Math.max(0, needed - b.stock) };
                });
            },
            get hasShortage() { return this.rows.some(r => r.short > 0); },
        };
    }
</script>
@endpush

@push('styles')
<style>[x-cloak]{display:none !important}</style>
@endpush
