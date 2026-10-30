@extends('layouts.system_layout')

@section('title', 'Sản xuất đầu ngày - The Daily Crust')

@php
    $rowsData = $plans->map(function ($plan) {
        $done = (int) $plan->passed_total;
        $approved = $plan->isApproved();
        $acked = $plan->isAcknowledged();
        $state = ! $approved ? 'pending' : (! $acked ? 'approved' : ($plan->remaining === 0 ? 'done' : 'working'));
        return [
            'id' => $plan->plan_id,
            'state' => $state,
            'name' => mb_strtolower($plan->product->vi_name ?? ''),
        ];
    })->values();
    $toAck = $rowsData->where('state', 'approved')->count();
@endphp

@section('content')
<div class="space-y-5" x-data="{ filter: 'all', q: '', rows: @js($rowsData),
        show(id) { const r = this.rows.find(x => x.id === id); if (!r) return true;
                   return (this.filter === 'all' || r.state === this.filter) && (this.q === '' || r.name.includes(this.q.toLowerCase())); },
        count(s) { return s === 'all' ? this.rows.length : this.rows.filter(r => r.state === s).length; } }">

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-mint-900"><i class="fa-solid fa-industry mr-2 text-mint-700"></i>Sản xuất ngày {{ $date->format('d/m/Y') }}</h1>
            <p class="mt-1 text-sm text-stone-500">Nhận kế hoạch đã duyệt, ghi nhận từng mẻ; hệ thống tự trừ nguyên liệu và nhập kho thành phẩm.</p>
        </div>
        <form method="GET" id="prodDateForm" class="flex items-center gap-2">
            <div class="relative">
                <i class="fa-regular fa-calendar pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-stone-400"></i>
                <input type="text" id="prodDate" name="date" value="{{ $date->toDateString() }}" readonly>
            </div>
        </form>
    </div>

    @include('staff.partials.flash')

    @if ($plans->isEmpty())
        <div class="rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center text-stone-500">
            <i class="fa-solid fa-industry mb-3 text-3xl text-mint-700/60"></i>
            <p class="font-medium">Chưa có kế hoạch sản xuất cho ngày này.</p>
            @if ($recentDates->isNotEmpty())
                <p class="mt-3 text-sm">Ngày có kế hoạch gần đây:</p>
                <div class="mt-2 flex flex-wrap justify-center gap-2">
                    @foreach ($recentDates as $d)
                        <a href="{{ route('admin.production.index', ['date' => $d]) }}" class="rounded-full border border-mint-700/30 bg-[#d8e8d2] px-3 py-1 text-xs font-semibold text-mint-800 hover:bg-[#c9ddc2]">{{ \Carbon\Carbon::parse($d)->format('d/m/Y') }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
            {{-- Thanh công cụ: lọc + tìm + xác nhận tất cả --}}
            <div class="flex flex-wrap items-center gap-3 border-b border-stone-200 px-4 py-3">
                <div class="flex flex-wrap gap-1.5 text-xs font-semibold">
                    @foreach (['all' => 'Tất cả', 'pending' => 'Chờ duyệt', 'approved' => 'Chờ xác nhận', 'working' => 'Đang làm', 'done' => 'Hoàn thành'] as $k => $label)
                        <button type="button" @click="filter='{{ $k }}'" class="rounded-full border px-3 py-1 transition"
                                :class="filter === '{{ $k }}' ? 'border-mint-700 bg-mint-700 text-white' : 'border-stone-200 bg-white text-stone-600 hover:border-mint-700/40'">
                            {{ $label }} <span class="tnum opacity-70" x-text="count('{{ $k }}')"></span>
                        </button>
                    @endforeach
                </div>
                <div class="relative ml-auto">
                    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-stone-400"></i>
                    <input type="search" x-model="q" placeholder="Tìm sản phẩm..." class="w-48 rounded-lg border border-stone-300 bg-white py-1.5 pl-8 pr-3 text-sm focus:border-mint-700 focus:outline-none focus:ring-2 focus:ring-mint-700/20">
                </div>
                @if ($isStaff && $toAck > 0)
                    @if ($canAcknowledge)
                        <form method="POST" action="{{ route('admin.production.acknowledge-all') }}" onsubmit="return confirm('Xác nhận nhận {{ $toAck }} kế hoạch đã duyệt của ngày {{ $date->format('d/m/Y') }}?')">
                            @csrf
                            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                            <button class="inline-flex items-center gap-1.5 rounded-lg bg-mint-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-mint-800"><i class="fa-solid fa-clipboard-check"></i>Xác nhận tất cả ({{ $toAck }})</button>
                        </form>
                    @else
                        <span class="text-xs text-stone-500">{{ $toAck }} kế hoạch chờ thợ làm bánh chính xác nhận.</span>
                    @endif
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 bg-stone-50 text-left text-[11px] font-semibold uppercase tracking-wide text-stone-500">
                            <th class="whitespace-nowrap px-4 py-3">Sản phẩm</th>
                            <th class="whitespace-nowrap px-3 py-3 text-right">Kế hoạch</th>
                            <th class="whitespace-nowrap px-3 py-3 text-right">Đạt</th>
                            <th class="whitespace-nowrap px-3 py-3 text-right">Lỗi</th>
                            <th class="whitespace-nowrap px-3 py-3 text-right">Còn lại</th>
                            <th class="whitespace-nowrap px-3 py-3">Tiến độ</th>
                            <th class="whitespace-nowrap px-3 py-3">Trạng thái</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($plans as $plan)
                            @php
                                $approved = $plan->isApproved();
                                $acked = $plan->isAcknowledged();
                                $done = (int) $plan->passed_total;
                                $pct = $plan->planned_qty > 0 ? min(100, round($done / $plan->planned_qty * 100)) : 0;
                                if (! $approved) { $badge = ['Chờ duyệt', 'bg-amber-100 text-amber-800']; }
                                elseif (! $acked) { $badge = ['Chờ xác nhận', 'bg-sky-100 text-sky-800']; }
                                elseif ($plan->remaining === 0) { $badge = ['Hoàn thành', 'bg-emerald-100 text-emerald-800']; }
                                else { $badge = ['Đang làm', 'bg-mint-700/10 text-mint-800']; }
                            @endphp
                            <tr x-show="show({{ $plan->plan_id }})" class="transition hover:bg-stone-50/80">
                                <td class="px-4 py-2.5 align-middle">
                                    <div class="font-semibold text-stone-900">{{ $plan->product->vi_name ?? 'Sản phẩm #'.$plan->product_id }}</div>
                                    <div class="text-[11px] text-stone-400">KH #{{ $plan->plan_id }}@if ($plan->adjustment_reason) · <span class="text-amber-700" title="{{ $plan->adjustment_reason }}"><i class="fa-solid fa-triangle-exclamation"></i> đã điều chỉnh</span>@endif</div>
                                    @if ($plan->shortages->isNotEmpty())
                                        <div class="mt-0.5 text-[11px] font-medium text-red-700" title="@foreach ($plan->shortages as $s){{ $s['name'] }}: cần {{ number_format($s['needed']) }}, còn {{ number_format($s['stock']) }} {{ $s['unit'] }}&#10;@endforeach"><i class="fa-solid fa-circle-exclamation"></i> Thiếu {{ $plan->shortages->count() }} nguyên liệu: {{ $plan->shortages->pluck('name')->take(2)->implode(', ') }}{{ $plan->shortages->count() > 2 ? '...' : '' }}</div>
                                    @endif
                                </td>
                                <td class="tnum px-3 py-2.5 text-right font-semibold text-stone-800">{{ $plan->planned_qty }}</td>
                                <td class="tnum px-3 py-2.5 text-right font-semibold text-emerald-700">{{ $done }}</td>
                                <td class="tnum px-3 py-2.5 text-right font-semibold {{ (int) $plan->failed_total ? 'text-red-700' : 'text-stone-300' }}">{{ (int) $plan->failed_total }}</td>
                                <td class="tnum px-3 py-2.5 text-right font-semibold text-stone-700">{{ $plan->remaining }}</td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2"><div class="h-1.5 w-24 rounded-full bg-stone-200"><div class="h-1.5 rounded-full bg-mint-700" style="width: {{ $pct }}%"></div></div><span class="tnum text-xs text-stone-500">{{ $pct }}%</span></div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5"><span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $badge[1] }}">{{ $badge[0] }}</span></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                    @if ($isStaff)
                                        @if ($approved && ! $acked && $canAcknowledge)
                                            <form method="POST" action="{{ route('admin.production.acknowledge', $plan) }}" class="inline">@csrf
                                                <button class="rounded-lg border border-mint-700 px-3 py-1 text-xs font-semibold text-mint-800 hover:bg-[#eef4ec]">Xác nhận</button>
                                            </form>
                                        @elseif ($approved && $acked && $canRecord && $plan->remaining > 0)
                                            <a href="{{ route('admin.production.batches.create', $plan) }}" class="inline-flex items-center gap-1 rounded-lg bg-mint-700 px-3 py-1 text-xs font-semibold text-white hover:bg-mint-800"><i class="fa-solid fa-plus"></i>Ghi nhận mẻ</a>
                                        @else
                                            <span class="text-stone-300">—</span>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Lịch sử mẻ --}}
    <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
        <div class="border-b border-stone-200 px-5 py-3 font-semibold text-mint-900">Lịch sử mẻ bánh gần đây</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 text-left text-[11px] font-semibold uppercase tracking-wide text-stone-500">
                    <tr><th class="whitespace-nowrap px-4 py-2.5">Mẻ</th><th class="whitespace-nowrap px-4 py-2.5">Thời gian</th><th class="whitespace-nowrap px-4 py-2.5">Sản phẩm</th><th class="whitespace-nowrap px-4 py-2.5 text-right">Đạt</th><th class="whitespace-nowrap px-4 py-2.5 text-right">Lỗi</th><th class="whitespace-nowrap px-4 py-2.5 text-right">Tỉ lệ đạt</th><th class="whitespace-nowrap px-4 py-2.5">Người làm</th><th class="whitespace-nowrap px-4 py-2.5">Ghi chú lỗi</th></tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($batches as $b)
                        <tr class="hover:bg-stone-50/80">
                            <td class="px-4 py-2 font-mono text-xs">#{{ $b->batch_id }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $b->batch_date?->format('H:i d/m/Y') }}</td>
                            <td class="px-4 py-2 font-medium">{{ $b->product->vi_name ?? '#'.$b->product_id }}</td>
                            <td class="tnum px-4 py-2 text-right font-semibold text-emerald-700">{{ $b->passed_qty }}</td>
                            <td class="tnum px-4 py-2 text-right font-semibold {{ $b->failed_qty ? 'text-red-700' : 'text-stone-300' }}">{{ $b->failed_qty }}</td>
                            <td class="tnum px-4 py-2 text-right">{{ $b->passRate() }}%</td>
                            <td class="px-4 py-2 text-stone-600">{{ $b->user->full_name ?? '—' }}</td>
                            <td class="px-4 py-2 text-xs text-stone-500">{{ $b->failure_note ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-stone-400">Chưa có mẻ nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<style>.flatpickr-day.selected{background:#3f6b3a;border-color:#3f6b3a}</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/vn.js"></script>
<script>
    flatpickr('#prodDate', {
        dateFormat: 'Y-m-d', altInput: true, altFormat: 'd/m/Y',
        altInputClass: 'w-36 cursor-pointer rounded-lg border border-stone-300 bg-white py-1.5 pl-9 pr-3 text-sm font-semibold text-stone-800 focus:border-mint-700 focus:outline-none',
        defaultDate: @json($date->toDateString()), disableMobile: true,
        locale: Object.assign({}, (window.flatpickr && flatpickr.l10ns && flatpickr.l10ns.vn) || {}, { firstDayOfWeek: 1 }),
        onChange: (sel, str) => { if (str) document.getElementById('prodDateForm').submit(); },
    });
</script>
@endpush
