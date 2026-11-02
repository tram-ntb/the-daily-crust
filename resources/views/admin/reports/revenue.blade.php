@extends('layouts.system_layout')

@section('title', 'Báo cáo & thống kê - The Daily Crust')

@section('content')
@php $pct = fn ($v) => $v === null ? '—' : number_format($v * 100, 1).'%'; @endphp
<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-mint-900"><i class="fa-solid fa-chart-column mr-2 text-mint-700"></i>Báo cáo &amp; thống kê</h1>
            <p class="mt-1 text-sm text-stone-500">Doanh thu, sản phẩm bán chạy, hao hụt và độ chính xác của mô hình dự báo trong khoảng thời gian đã chọn.</p>
        </div>
        <form method="GET" id="rangeForm" class="flex flex-wrap items-center gap-2">
            @foreach ([7 => '7 ngày', 14 => '14 ngày', 30 => '30 ngày'] as $n => $l)
                <a href="{{ route('admin.reports.revenue', ['from' => now()->subDays($n - 1)->toDateString(), 'to' => now()->toDateString()]) }}" class="rounded-lg border border-stone-200 bg-white px-3 py-1.5 text-xs font-semibold text-stone-600 hover:border-mint-700/40">{{ $l }}</a>
            @endforeach
            <input type="text" id="range" readonly>
            <input type="hidden" name="from" id="fromInput" value="{{ $from->toDateString() }}"><input type="hidden" name="to" id="toInput" value="{{ $to->toDateString() }}">
        </form>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([['Doanh thu', number_format($totalRevenue).'đ', 'fa-coins'], ['Số đơn', number_format($totalOrders), 'fa-receipt'], ['Giá trị TB / đơn', number_format($avgOrder).'đ', 'fa-scale-balanced'], ['Giá trị hủy hàng', number_format($waste).'đ', 'fa-trash-can']] as [$l, $v, $i])
            <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase tracking-wider text-stone-500"><i class="fa-solid {{ $i }} mr-1 text-mint-700"></i>{{ $l }}</p><p class="mt-1 text-2xl font-extrabold tnum {{ $l === 'Giá trị hủy hàng' ? 'text-red-700' : 'text-stone-800' }}">{{ $v }}</p></div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
        <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm xl:col-span-2">
            <div class="border-b border-stone-200 px-5 py-3 font-semibold text-mint-900">Doanh thu theo ngày</div>
            <div class="max-h-96 overflow-auto"><table class="w-full text-sm">
                <thead class="sticky top-0 bg-stone-50 text-left text-[11px] font-semibold uppercase tracking-wide text-stone-500"><tr><th class="px-4 py-2">Ngày</th><th class="px-4 py-2 text-right">Đơn</th><th class="px-4 py-2 text-right">Doanh thu</th><th class="w-1/3 px-4 py-2"></th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($byDay as $d)
                        <tr><td class="whitespace-nowrap px-4 py-2">{{ \Carbon\Carbon::parse($d->d)->format('d/m/Y') }}</td><td class="px-4 py-2 text-right tnum">{{ $d->orders }}</td><td class="whitespace-nowrap px-4 py-2 text-right font-semibold tnum">{{ number_format($d->revenue) }}đ</td>
                            <td class="px-4 py-2"><div class="h-2 rounded-full bg-stone-100"><div class="h-2 rounded-full bg-mint-700" style="width: {{ round($d->revenue / $maxRevenue * 100) }}%"></div></div></td></tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-stone-400">Không có đơn hàng trong khoảng này.</td></tr>
                    @endforelse
                </tbody></table></div>
        </div>
        <div class="space-y-5">
            <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
                <div class="border-b border-stone-200 px-5 py-3 font-semibold text-mint-900">Theo kênh bán</div>
                <table class="w-full text-sm"><tbody class="divide-y divide-stone-100">
                    @forelse ($channels as $c)<tr><td class="px-4 py-2">{{ $c->ch === 'POS' ? 'Tại quầy (POS)' : 'Đặt online' }}</td><td class="px-4 py-2 text-right tnum">{{ $c->orders }} đơn</td><td class="whitespace-nowrap px-4 py-2 text-right font-semibold tnum">{{ number_format($c->revenue) }}đ</td></tr>
                    @empty<tr><td class="px-4 py-6 text-center text-stone-400">Không có dữ liệu.</td></tr>@endforelse
                </tbody></table>
            </div>
            <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
                <div class="border-b border-stone-200 px-5 py-3 font-semibold text-mint-900">Bán chạy nhất</div>
                <table class="w-full text-sm"><tbody class="divide-y divide-stone-100">
                    @forelse ($top as $i => $t)<tr><td class="px-4 py-2"><span class="mr-2 text-xs text-stone-400">{{ $i + 1 }}</span>{{ $t->name }}</td><td class="whitespace-nowrap px-4 py-2 text-right font-semibold tnum">{{ number_format($t->units) }}</td></tr>
                    @empty<tr><td class="px-4 py-6 text-center text-stone-400">Không có dữ liệu.</td></tr>@endforelse
                </tbody></table>
            </div>
        </div>
    </div>

    {{-- Độ chính xác dự báo: điểm nhấn của đồ án --}}
    <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-stone-200 px-5 py-3">
            <span class="font-semibold text-mint-900"><i class="fa-solid fa-bullseye mr-1.5 text-mint-700"></i>Độ chính xác dự báo (dự báo so với bán thực tế)</span>
            @if ($accuracy['model'])<span class="text-xs text-stone-500">Mô hình: <b>{{ $accuracy['model'] }}</b></span>@endif
        </div>
        @if ($accuracy['days']->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-stone-400">Chưa có ngày nào vừa có dự báo vừa có dữ liệu bán thực tế trong khoảng này. Hãy chọn khoảng ngày đã có dự báo.</p>
        @else
            <div class="grid grid-cols-2 gap-3 border-b border-stone-100 p-4 lg:grid-cols-3">
                <div class="rounded-lg bg-stone-50 p-3"><p class="text-xs font-bold uppercase tracking-wider text-stone-500">WAPE (sản phẩm–ngày)</p><p class="text-2xl font-extrabold tnum text-stone-800">{{ $pct($accuracy['wape']) }}</p></div>
                <div class="rounded-lg bg-stone-50 p-3"><p class="text-xs font-bold uppercase tracking-wider text-stone-500">MAE / sản phẩm–ngày</p><p class="text-2xl font-extrabold tnum text-stone-800">{{ number_format($accuracy['mae'], 2) }}</p></div>
                <div class="rounded-lg bg-stone-50 p-3"><p class="text-xs font-bold uppercase tracking-wider text-stone-500">Số ngày đối chiếu</p><p class="text-2xl font-extrabold tnum text-stone-800">{{ $accuracy['days']->count() }}</p></div>
            </div>
            <div class="max-h-80 overflow-auto"><table class="w-full text-sm">
                <thead class="sticky top-0 bg-stone-50 text-left text-[11px] font-semibold uppercase tracking-wide text-stone-500"><tr><th class="px-4 py-2">Ngày</th><th class="px-4 py-2 text-right">Dự báo (tổng)</th><th class="px-4 py-2 text-right">Thực tế bán</th><th class="px-4 py-2 text-right">Sai lệch tổng ngày (WAPE)</th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($accuracy['days'] as $d)
                        <tr><td class="whitespace-nowrap px-4 py-2">{{ \Carbon\Carbon::parse($d['date'])->format('d/m/Y') }}</td><td class="px-4 py-2 text-right tnum">{{ $d['pred'] }}</td><td class="px-4 py-2 text-right tnum">{{ $d['act'] }}</td>
                            <td class="px-4 py-2 text-right tnum font-semibold {{ ($d['wape'] ?? 1) <= 0.2 ? 'text-emerald-700' : (($d['wape'] ?? 1) <= 0.4 ? 'text-amber-700' : 'text-red-700') }}">{{ $pct($d['wape']) }}</td></tr>
                    @endforeach
                </tbody></table></div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<style>.flatpickr-day.selected,.flatpickr-day.startRange,.flatpickr-day.endRange{background:#3f6b3a;border-color:#3f6b3a}.flatpickr-day.inRange{background:#d8e8d2;border-color:#d8e8d2;box-shadow:none}</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/vn.js"></script>
<script>
    flatpickr('#range', { mode: 'range', dateFormat: 'Y-m-d', altInput: true, altFormat: 'd/m/Y', disableMobile: true,
        defaultDate: [@json($from->toDateString()), @json($to->toDateString())],
        altInputClass: 'w-56 cursor-pointer rounded-lg border border-stone-300 bg-white px-3 py-1.5 text-sm font-semibold text-stone-800 focus:border-mint-700 focus:outline-none',
        locale: Object.assign({}, (window.flatpickr && flatpickr.l10ns && flatpickr.l10ns.vn) || {}, { firstDayOfWeek: 1, rangeSeparator: ' → ' }),
        onChange: (sel) => { if (sel.length === 2) { const f = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            document.getElementById('fromInput').value = f(sel[0]); document.getElementById('toInput').value = f(sel[1]); document.getElementById('rangeForm').submit(); } } });
</script>
@endpush
