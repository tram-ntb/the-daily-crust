@extends('layouts.system_layout')

@section('title', $session->session_name.' - The Daily Crust')

@section('content')
@php
    $codeStyle = [
        'MATCH' => ['bg-emerald-100 text-emerald-800', 'Khớp'],
        'WITHIN_TOLERANCE' => ['bg-sky-100 text-sky-800', 'Trong dung sai'],
        'OVER_TOLERANCE' => ['bg-red-100 text-red-800', 'Vượt dung sai'],
    ];
@endphp
<div class="space-y-5">
    <div>
        <a href="{{ route('admin.stocktake.index') }}" class="text-sm text-mint-700 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i>Danh sách phiên</a>
        <h1 class="mt-1 text-2xl font-bold text-mint-900">{{ $session->session_name }}</h1>
        <p class="text-sm text-stone-500">Trạng thái: <b>{{ $session->status }}</b> @if($session->note) · {{ $session->note }} @endif</p>
    </div>

    @include('staff.partials.flash')

    {{-- Biên bản giải trình hao hụt + duyệt của Quản lý --}}
    @if ($session->status !== 'In_Progress' && $overCount > 0)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
            <div class="text-sm text-amber-900">
                <p class="font-semibold"><i class="fa-solid fa-file-signature mr-1"></i>{{ $overCount }} mặt hàng vượt dung sai cần biên bản giải trình hao hụt.</p>
                @if ($session->status === 'Pending_Approval')
                    <p class="mt-0.5 text-xs">{{ $isManager ? 'Xem biên bản rồi duyệt (điều chỉnh tồn kho) hoặc yêu cầu đếm lại.' : 'Hãy điền biên bản để Quản lý xem xét và phê duyệt.' }}</p>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.stocktake.report', $session) }}" class="rounded-lg border border-amber-700 px-4 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-100"><i class="fa-solid fa-file-lines mr-1"></i>Biên bản giải trình</a>
                @if ($isManager && $session->status === 'Pending_Approval')
                    <form method="POST" action="{{ route('admin.stocktake.approve', $session) }}" onsubmit="return confirm('Duyệt và điều chỉnh tồn kho theo số thực tế?')">
                        @csrf
                        <button class="rounded-lg bg-mint-700 px-4 py-2 text-sm font-semibold text-white hover:bg-mint-800"><i class="fa-solid fa-check mr-1"></i>Duyệt &amp; điều chỉnh kho</button>
                    </form>
                    <form method="POST" action="{{ route('admin.stocktake.reject', $session) }}" class="flex items-center gap-1.5">
                        @csrf
                        <input type="text" name="reason" maxlength="150" required placeholder="Lý do yêu cầu đếm lại" class="w-52 rounded-lg border border-stone-300 px-2.5 py-2 text-xs focus:border-mint-700 focus:outline-none">
                        <button class="rounded-lg border border-red-600 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Đếm lại</button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.stocktake.update', $session) }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-[#d8e8d2] text-left text-xs font-bold uppercase tracking-wider text-mint-900">
                    <tr><th class="px-4 py-3">Mặt hàng</th><th class="px-4 py-3">Loại</th><th class="px-4 py-3 text-right">Số hệ thống</th><th class="px-4 py-3 text-right">Số thực tế</th><th class="px-4 py-3 text-right">Chênh lệch</th><th class="px-4 py-3">Đánh giá</th><th class="px-4 py-3">Giải trình</th></tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                @foreach ($details as $d)
                    @php
                        $tol = $d->item_type === 'Ingredient' && $d->ingredient?->tolerance_rate !== null ? (float) $d->ingredient->tolerance_rate : $defaultTol;
                        $code = $codeStyle[$d->reason_code] ?? null;
                    @endphp
                    @if ($editable)
                    <tr x-data="stocktakeRow({ system: {{ (int) $d->system_qty }}, tol: {{ $tol }}, actual: @js(old('counts.'.$d->stocktake_detail_id.'.actual', $d->actual_qty)) })"
                        :class="state === 'over' ? 'bg-red-50/70' : ''">
                        <td class="px-4 py-2 font-medium text-stone-800">{{ $d->label() }}</td>
                        <td class="px-4 py-2 text-xs text-stone-500">{{ $d->item_type === 'Ingredient' ? 'Nguyên liệu' : 'Thành phẩm' }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($d->system_qty) }} <span class="text-xs text-stone-400">{{ $d->unit }}</span></td>
                        <td class="px-4 py-2 text-right">
                            <input type="number" min="0" name="counts[{{ $d->stocktake_detail_id }}][actual]" x-model="actual"
                                   class="w-28 rounded-lg border border-stone-300 px-2 py-1 text-right focus:border-mint-700 focus:outline-none">
                        </td>
                        <td class="px-4 py-2 text-right font-semibold" :class="variance < 0 ? 'text-red-700' : (variance > 0 ? 'text-emerald-700' : 'text-stone-400')"
                            x-text="counted ? (variance > 0 ? '+' : '') + variance + ' (' + pct.toFixed(1) + '%)' : '—'"></td>
                        <td class="px-4 py-2">
                            <span x-show="state === 'match'" class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Khớp</span>
                            <span x-show="state === 'ok'" x-cloak class="rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-semibold text-sky-800">Trong dung sai {{ rtrim(rtrim(number_format($tol, 1), '0'), '.') }}%</span>
                            <span x-show="state === 'over'" x-cloak class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800">Vượt dung sai {{ rtrim(rtrim(number_format($tol, 1), '0'), '.') }}%</span>
                        </td>
                        <td class="px-4 py-2">
                            <input type="text" maxlength="200" name="counts[{{ $d->stocktake_detail_id }}][explanation]"
                                   value="{{ old('counts.'.$d->stocktake_detail_id.'.explanation', $d->explanation) }}"
                                   x-show="state === 'over'" x-cloak placeholder="Bắt buộc giải trình..."
                                   class="w-56 rounded-lg border border-red-300 px-2 py-1 text-xs focus:outline-none">
                        </td>
                    </tr>
                    @else
                    <tr>
                        <td class="px-4 py-2 font-medium text-stone-800">{{ $d->label() }}</td>
                        <td class="px-4 py-2 text-xs text-stone-500">{{ $d->item_type === 'Ingredient' ? 'Nguyên liệu' : 'Thành phẩm' }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($d->system_qty) }} <span class="text-xs text-stone-400">{{ $d->unit }}</span></td>
                        <td class="px-4 py-2 text-right">{{ $d->actual_qty !== null ? number_format($d->actual_qty) : '—' }}</td>
                        <td class="px-4 py-2 text-right font-semibold {{ ($d->variance_qty ?? 0) < 0 ? 'text-red-700' : (($d->variance_qty ?? 0) > 0 ? 'text-emerald-700' : 'text-stone-400') }}">
                            {{ $d->variance_qty !== null ? (($d->variance_qty > 0 ? '+' : '').$d->variance_qty.' ('.number_format($d->variance_percentage, 1).'%)') : '—' }}
                        </td>
                        <td class="px-4 py-2">@if ($code)<span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $code[0] }}">{{ $code[1] }}</span>@endif</td>
                        <td class="px-4 py-2 text-xs text-stone-600">{{ $d->explanation }}</td>
                    </tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>

        @if ($editable)
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-stone-500">Dòng để trống sẽ không được đối soát. Khi chốt, nếu có dòng vượt dung sai, phiên chuyển sang chờ Quản lý duyệt và chưa điều chỉnh kho.</p>
                <div class="flex gap-2">
                    <button name="action" value="save" class="rounded-lg border border-mint-700 px-4 py-2 text-sm font-semibold text-mint-800 hover:bg-[#d8e8d2]">Lưu tạm</button>
                    <button name="action" value="submit" onclick="return confirm('Chốt kiểm kê? Sau khi chốt sẽ không sửa được.')" class="rounded-lg bg-mint-700 px-5 py-2 text-sm font-semibold text-white hover:bg-mint-800"><i class="fa-solid fa-check mr-1"></i>Chốt kiểm kê</button>
                </div>
            </div>
        @endif
    </form>
</div>
@endsection

@push('scripts')
@if ($editable)
<script>
    function stocktakeRow(cfg) {
        return {
            actual: cfg.actual ?? '',
            get counted() { return this.actual !== '' && this.actual !== null; },
            get variance() { return this.counted ? Number(this.actual) - cfg.system : 0; },
            get pct() { return cfg.system > 0 ? Math.abs(this.variance) / cfg.system * 100 : (this.variance !== 0 ? 100 : 0); },
            get state() {
                if (!this.counted) return 'none';
                if (this.variance === 0) return 'match';
                return this.pct <= cfg.tol ? 'ok' : 'over';
            },
        };
    }
</script>
@endif
@endpush

@push('styles')
<style>[x-cloak]{display:none !important}</style>
@endpush
