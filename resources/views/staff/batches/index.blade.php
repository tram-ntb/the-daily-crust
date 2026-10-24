@extends('layouts.system_layout')

@section('title', 'Theo dõi mẻ nướng - The Daily Crust')

@section('content')
@php
    $maxFail = max(1, (int) ($failureStats->max('qty') ?? 1));
@endphp
<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-mint-900"><i class="fa-solid fa-fire-burner mr-2 text-mint-700"></i>Theo dõi mẻ nướng</h1>
            <p class="mt-1 text-sm text-stone-500">Kết quả thực tế từng mẻ, lô thành phẩm sinh ra, loại lỗi và độ lệch so với dự báo / kế hoạch.</p>
        </div>
        <a href="{{ route('admin.production.index') }}" class="rounded-lg border border-mint-700 px-4 py-2 text-sm font-semibold text-mint-800 hover:bg-[#d8e8d2]"><i class="fa-solid fa-industry mr-1"></i>Kế hoạch hôm nay</a>
    </div>

    @include('staff.partials.flash')

    {{-- Bộ lọc --}}
    <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
        <label class="block text-xs font-bold uppercase tracking-wider text-stone-500">Từ ngày
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="mt-1 block rounded-lg border border-stone-300 px-3 py-2 text-sm font-normal normal-case text-stone-800">
        </label>
        <label class="block text-xs font-bold uppercase tracking-wider text-stone-500">Đến ngày
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="mt-1 block rounded-lg border border-stone-300 px-3 py-2 text-sm font-normal normal-case text-stone-800">
        </label>
        <label class="block text-xs font-bold uppercase tracking-wider text-stone-500">Sản phẩm
            <select name="product_id" class="mt-1 block rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm font-normal normal-case text-stone-800">
                <option value="">Tất cả</option>
                @foreach ($products as $p)
                    <option value="{{ $p->product_id }}" @selected($productId === (int) $p->product_id)>{{ $p->vi_name }}</option>
                @endforeach
            </select>
        </label>
        <button class="rounded-lg bg-mint-700 px-4 py-2 text-sm font-semibold text-white hover:bg-mint-800">Xem</button>
        <span class="text-xs text-stone-400">Tối đa 31 ngày mỗi lần xem.</span>
    </form>

    {{-- Tổng quan --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
        <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase tracking-wider text-stone-500">Số mẻ</p><p class="mt-1 text-2xl font-extrabold text-stone-800">{{ number_format($totals['batches']) }}</p></div>
        <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase tracking-wider text-stone-500">Đã làm</p><p class="mt-1 text-2xl font-extrabold text-stone-800">{{ number_format($totals['produced']) }}</p></div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm"><p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Đạt chuẩn</p><p class="mt-1 text-2xl font-extrabold text-emerald-800">{{ number_format($totals['passed']) }}</p></div>
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm"><p class="text-xs font-bold uppercase tracking-wider text-red-700">Lỗi / hỏng</p><p class="mt-1 text-2xl font-extrabold text-red-800">{{ number_format($totals['failed']) }}</p></div>
        <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase tracking-wider text-stone-500">Tỉ lệ đạt</p><p class="mt-1 text-2xl font-extrabold {{ ($totals['rate'] ?? 100) < 90 ? 'text-red-700' : 'text-mint-800' }}">{{ $totals['rate'] === null ? '—' : $totals['rate'].'%' }}</p></div>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        {{-- Loại lỗi --}}
        <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 font-semibold text-mint-900"><i class="fa-solid fa-bug mr-1 text-red-600"></i>Bánh hỏng theo loại lỗi</h2>
            @forelse ($failureStats as $f)
                <div class="mb-3">
                    <div class="flex items-baseline justify-between text-sm">
                        <span class="font-medium text-stone-800">{{ $f['type'] }}</span>
                        <span class="font-bold text-red-700">{{ number_format($f['qty']) }} <span class="text-xs font-normal text-stone-400">· {{ number_format($f['cost']) }}đ</span></span>
                    </div>
                    <div class="mt-1 h-2 rounded-full bg-stone-100"><div class="h-2 rounded-full bg-red-500/80" style="width: {{ round($f['qty'] / $maxFail * 100) }}%"></div></div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-stone-400">Không có bánh hỏng trong khoảng này.</p>
            @endforelse
            <p class="mt-2 text-[11px] text-stone-400">Lấy từ phiếu hủy "Lỗi sản xuất" tự lập khi ghi mẻ.</p>
        </div>

        {{-- Đối chiếu Dự báo - Kế hoạch - Thực tế --}}
        <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm lg:col-span-2">
            <div class="border-b border-stone-200 px-5 py-3 font-semibold text-mint-900"><i class="fa-solid fa-scale-balanced mr-1 text-mint-700"></i>Đối chiếu Dự báo · Kế hoạch · Thực tế theo ngày</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 text-left text-xs font-bold uppercase tracking-wider text-stone-500">
                        <tr><th class="px-4 py-2">Ngày</th><th class="px-4 py-2 text-right">Dự báo</th><th class="px-4 py-2 text-right">Kế hoạch</th><th class="px-4 py-2 text-right">Làm đạt</th><th class="px-4 py-2 text-right">Lỗi</th><th class="px-4 py-2 text-right">Đạt / Kế hoạch</th><th class="px-4 py-2 text-right">Lệch so với dự báo</th></tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($daily as $d)
                            @php
                                $achv = $d['planned'] ? round($d['passed'] / $d['planned'] * 100) : null;
                                $diff = $d['forecast'] !== null && $d['passed'] > 0 ? $d['passed'] - $d['forecast'] : null;
                            @endphp
                            <tr>
                                <td class="px-4 py-2 font-medium">{{ $d['date']->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 text-right">{{ $d['forecast'] ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">{{ $d['planned'] ?? '—' }}</td>
                                <td class="px-4 py-2 text-right font-semibold text-emerald-700">{{ $d['passed'] }}</td>
                                <td class="px-4 py-2 text-right {{ $d['failed'] ? 'font-semibold text-red-700' : 'text-stone-400' }}">{{ $d['failed'] }}</td>
                                <td class="px-4 py-2 text-right">{{ $achv === null ? '—' : $achv.'%' }}</td>
                                <td class="px-4 py-2 text-right font-semibold {{ $diff === null ? 'text-stone-300' : ($diff < 0 ? 'text-red-700' : 'text-sky-700') }}">{{ $diff === null ? '—' : ($diff > 0 ? '+' : '').$diff }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-stone-400">Chưa có dữ liệu trong khoảng này.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Danh sách mẻ + lô thành phẩm --}}
    <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
        <div class="border-b border-stone-200 px-5 py-3 font-semibold text-mint-900">Chi tiết từng mẻ và lô thành phẩm</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 text-left text-xs font-bold uppercase tracking-wider text-stone-500">
                    <tr>
                        <th class="px-4 py-2">Mẻ</th><th class="px-4 py-2">Thời gian</th><th class="px-4 py-2">Sản phẩm</th>
                        <th class="px-4 py-2 text-right">Đạt</th><th class="px-4 py-2 text-right">Lỗi</th><th class="px-4 py-2 text-right">Tỉ lệ đạt</th>
                        <th class="px-4 py-2">Lô thành phẩm</th><th class="px-4 py-2">Người làm</th><th class="px-4 py-2">Loại lỗi / ghi chú</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($batches as $b)
                        @php $lot = $b->lot; @endphp
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs">#{{ $b->batch_id }}@if($b->plan_id)<span class="block text-[11px] text-stone-400">KH #{{ $b->plan_id }}</span>@endif</td>
                            <td class="px-4 py-2 text-stone-600">{{ $b->batch_date?->format('H:i d/m/Y') }}@if($b->start_time)<span class="block text-[11px] text-stone-400">bắt đầu {{ \Illuminate\Support\Str::limit($b->start_time, 5, '') }}</span>@endif</td>
                            <td class="px-4 py-2 font-medium">{{ $b->product->vi_name ?? '#'.$b->product_id }}</td>
                            <td class="px-4 py-2 text-right font-semibold text-emerald-700">{{ $b->passed_qty }}</td>
                            <td class="px-4 py-2 text-right font-semibold {{ $b->failed_qty ? 'text-red-700' : 'text-stone-400' }}">{{ $b->failed_qty }}</td>
                            <td class="px-4 py-2 text-right">{{ $b->passRate() }}%</td>
                            <td class="px-4 py-2 text-xs">
                                @if ($lot)
                                    <span class="font-mono">Lô #{{ $lot->lot_id }}</span>
                                    <span class="text-stone-500">· còn {{ $lot->remaining_qty }}/{{ $lot->initial_qty }}</span>
                                    <span class="block text-stone-400">HSD {{ $lot->expiry_date?->format('H:i d/m') }}
                                        @if ($lot->remaining_qty <= 0)<span class="font-semibold text-stone-500">· hết hàng</span>
                                        @elseif ($lot->isExpired())<span class="font-semibold text-red-700">· quá hạn</span>@endif
                                    </span>
                                @else
                                    <span class="text-stone-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-stone-600">{{ $b->user->full_name ?? '—' }}</td>
                            <td class="px-4 py-2 text-xs text-stone-600">{{ $b->failure_note ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-10 text-center text-stone-400">Chưa có mẻ nào trong khoảng này.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-stone-100 px-4 py-3">{{ $batches->links() }}</div>
    </div>
</div>
@endsection
