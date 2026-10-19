@extends('layouts.system_layout')

@section('title', 'Sản xuất đầu ngày - The Daily Crust')

@section('content')
<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-mint-900">Kế hoạch sản xuất ngày {{ $date->format('d/m/Y') }}</h1>
            <p class="mt-1 text-sm text-stone-500">Nhận kế hoạch đã duyệt, ghi nhận từng mẻ bánh; hệ thống tự trừ nguyên liệu và nhập kho thành phẩm.</p>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <input type="date" name="date" value="{{ $date->toDateString() }}" onchange="this.form.submit()"
                   class="rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm focus:border-mint-700 focus:outline-none">
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
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($plans as $plan)
                @php
                    $approved = $plan->isApproved();
                    $acked = $plan->isAcknowledged();
                    $done = (int) $plan->passed_total;
                    $pct = $plan->planned_qty > 0 ? min(100, round($done / $plan->planned_qty * 100)) : 0;
                @endphp
                <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-mint-900">{{ $plan->product->vi_name ?? 'Sản phẩm #'.$plan->product_id }}</h2>
                            <p class="text-xs text-stone-500">Kế hoạch #{{ $plan->plan_id }} · {{ $plan->plan_status }}</p>
                        </div>
                        <div class="flex flex-col items-end gap-1 text-xs font-semibold">
                            <span class="rounded-full px-2.5 py-0.5 {{ $approved ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $approved ? 'Đã duyệt' : 'Chờ duyệt' }}</span>
                            <span class="rounded-full px-2.5 py-0.5 {{ $acked ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-100 text-stone-600' }}">{{ $acked ? 'Đã xác nhận' : 'Chưa xác nhận' }}</span>
                        </div>
                    </div>

                    @if ($plan->adjustment_reason)
                        <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800"><i class="fa-solid fa-triangle-exclamation mr-1"></i>{{ $plan->adjustment_reason }}</p>
                    @endif

                    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg bg-stone-50 p-2"><div class="text-xs text-stone-500">Kế hoạch</div><div class="text-xl font-bold text-stone-800">{{ $plan->planned_qty }}</div></div>
                        <div class="rounded-lg bg-emerald-50 p-2"><div class="text-xs text-emerald-700">Đã đạt</div><div class="text-xl font-bold text-emerald-800">{{ $done }}</div></div>
                        <div class="rounded-lg bg-red-50 p-2"><div class="text-xs text-red-700">Lỗi</div><div class="text-xl font-bold text-red-800">{{ (int) $plan->failed_total }}</div></div>
                    </div>
                    <div class="mt-3 h-2 rounded-full bg-stone-200"><div class="h-2 rounded-full bg-mint-700" style="width: {{ $pct }}%"></div></div>
                    <p class="mt-1 text-xs text-stone-500">Còn {{ $plan->remaining }} sản phẩm cần làm ({{ $pct }}%)</p>

                    @if ($plan->shortages->isNotEmpty())
                        <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
                            <p class="font-semibold"><i class="fa-solid fa-circle-exclamation mr-1"></i>Thiếu nguyên liệu để làm phần còn lại:</p>
                            <ul class="mt-1 list-disc pl-5">
                                @foreach ($plan->shortages as $s)
                                    <li>{{ $s['name'] }}: cần {{ number_format($s['needed']) }}, còn {{ number_format($s['stock']) }} {{ $s['unit'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($isStaff)
                        <div class="mt-4 flex flex-wrap gap-2">
                            @if ($approved && ! $acked)
                                @if ($canAcknowledge)
                                    <form method="POST" action="{{ route('admin.production.acknowledge', $plan) }}">
                                        @csrf
                                        <button class="rounded-lg bg-mint-700 px-4 py-2 text-sm font-semibold text-white hover:bg-mint-800"><i class="fa-solid fa-clipboard-check mr-1"></i>Xác nhận đã nhận kế hoạch</button>
                                    </form>
                                @else
                                    <span class="text-xs text-stone-500">Chờ thợ làm bánh chính xác nhận kế hoạch.</span>
                                @endif
                            @endif
                            @if ($approved && $acked && $canRecord && $plan->remaining > 0)
                                <a href="{{ route('admin.production.batches.create', $plan) }}" class="rounded-lg bg-mint-700 px-4 py-2 text-sm font-semibold text-white hover:bg-mint-800"><i class="fa-solid fa-plus mr-1"></i>Ghi nhận mẻ bánh</a>
                            @endif
                            @if ($plan->remaining === 0)
                                <span class="text-xs font-semibold text-emerald-700"><i class="fa-solid fa-circle-check mr-1"></i>Đã hoàn thành kế hoạch</span>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Lịch sử mẻ --}}
    <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
        <div class="border-b border-stone-200 px-5 py-3 font-semibold text-mint-900">Lịch sử mẻ bánh gần đây</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 text-left text-xs font-bold uppercase tracking-wider text-stone-500">
                    <tr><th class="px-4 py-2">Mẻ</th><th class="px-4 py-2">Thời gian</th><th class="px-4 py-2">Sản phẩm</th><th class="px-4 py-2 text-right">Đạt</th><th class="px-4 py-2 text-right">Lỗi</th><th class="px-4 py-2 text-right">Tỉ lệ đạt</th><th class="px-4 py-2">Người làm</th><th class="px-4 py-2">Ghi chú lỗi</th></tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($batches as $b)
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs">#{{ $b->batch_id }}</td>
                            <td class="px-4 py-2 text-stone-600">{{ $b->batch_date?->format('H:i d/m/Y') }}</td>
                            <td class="px-4 py-2 font-medium">{{ $b->product->vi_name ?? '#'.$b->product_id }}</td>
                            <td class="px-4 py-2 text-right font-semibold text-emerald-700">{{ $b->passed_qty }}</td>
                            <td class="px-4 py-2 text-right font-semibold {{ $b->failed_qty ? 'text-red-700' : 'text-stone-400' }}">{{ $b->failed_qty }}</td>
                            <td class="px-4 py-2 text-right">{{ $b->passRate() }}%</td>
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
