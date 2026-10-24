@extends('layouts.system_layout')

@section('title', 'Hàng cận date & tồn đọng - The Daily Crust')

@section('content')
@php
    $stateStyle = [
        'expired' => ['bg-red-100 text-red-800', 'Quá hạn', 'bg-red-500'],
        'near' => ['bg-amber-100 text-amber-800', 'Cận date', 'bg-amber-500'],
        'ok' => ['bg-emerald-50 text-emerald-800', 'Còn tốt', 'bg-emerald-500'],
    ];
    $tabs = ['attention' => 'Cần xử lý', 'expired' => 'Quá hạn', 'near' => 'Cận date', 'ok' => 'Còn tốt', 'all' => 'Tất cả'];
    $human = function (?float $h) {
        if ($h === null) return 'Không có hạn';
        $m = (int) round(abs($h) * 60);
        $txt = intdiv($m, 60).' giờ '.str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT).' phút';
        return $h <= 0 ? 'Quá hạn '.$txt : 'Còn '.$txt;
    };
@endphp
<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-mint-900"><i class="fa-solid fa-hourglass-half mr-2 text-mint-700"></i>Hàng cận date &amp; tồn đọng</h1>
            <p class="mt-1 text-sm text-stone-500">Tự nhận diện thành phẩm quá hạn và cận date (còn ≤ {{ $nearPercent }}% thời hạn sử dụng) dựa trên hạn dùng của từng lô, để lập phiếu hủy kịp thời.</p>
        </div>
        @if ($canWrite && $summary['expired']['lots'] > 0)
            <form method="POST" action="{{ route('admin.expiry.destroy-expired') }}"
                  onsubmit="return confirm('Hủy toàn bộ {{ $summary['expired']['qty'] }} sản phẩm quá hạn ({{ $summary['expired']['lots'] }} lô)?')">
                @csrf
                <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"><i class="fa-solid fa-trash-can mr-1"></i>Hủy tất cả lô quá hạn</button>
            </form>
        @endif
    </div>

    @include('staff.partials.flash')

    {{-- Tổng quan --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        @foreach (['expired' => 'Quá hạn - phải hủy', 'near' => 'Cận date - ưu tiên bán / xử lý', 'ok' => 'Còn tốt'] as $key => $label)
            @php $sum = $summary[$key]; $st = $stateStyle[$key]; @endphp
            <div class="rounded-xl border p-4 shadow-sm {{ $key === 'expired' && $sum['lots'] ? 'border-red-200 bg-red-50' : ($key === 'near' && $sum['lots'] ? 'border-amber-200 bg-amber-50' : 'border-stone-200 bg-white') }}">
                <p class="text-xs font-bold uppercase tracking-wider text-stone-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-extrabold text-stone-800">{{ number_format($sum['qty']) }} <span class="text-sm font-semibold text-stone-500">sản phẩm · {{ $sum['lots'] }} lô</span></p>
                <p class="text-xs text-stone-500">Giá vốn: {{ number_format($sum['value']) }} đ</p>
            </div>
        @endforeach
    </div>

    {{-- Tab lọc --}}
    <div class="flex flex-wrap gap-2 border-b border-stone-200">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.expiry.index', ['state' => $key]) }}"
               class="-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold {{ $state === $key ? 'border-mint-700 text-mint-800' : 'border-transparent text-stone-500 hover:text-mint-700' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#d8e8d2] text-left text-xs font-bold uppercase tracking-wider text-mint-900">
                    <tr>
                        <th class="px-4 py-3">Sản phẩm / Lô</th><th class="px-4 py-3">Tình trạng</th><th class="px-4 py-3">Hạn dùng</th>
                        <th class="px-4 py-3 text-right">Còn lại</th><th class="px-4 py-3 text-right">Giá vốn</th>
                        <th class="px-4 py-3 text-right" title="Dự báo cần sản xuất cho ngày {{ $planDate->format('d/m') }}">Dự báo SX {{ $planDate->format('d/m') }}</th>
                        @if ($canWrite)<th class="px-4 py-3">Lập phiếu hủy</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($lots as $row)
                        @php $lot = $row['lot']; $st = $stateStyle[$row['state']]; @endphp
                        <tr class="{{ $row['state'] === 'expired' ? 'bg-red-50/50' : ($row['state'] === 'near' ? 'bg-amber-50/50' : '') }}">
                            <td class="px-4 py-2.5">
                                <span class="block font-bold text-stone-900">{{ $lot->product->vi_name ?? 'SP #'.$lot->product_id }}</span>
                                <span class="font-mono text-xs text-stone-400">Lô #{{ $lot->lot_id }} · SX {{ $lot->production_date?->format('H:i d/m') }}</span>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $st[0] }}">{{ $st[1] }}</span>
                                @if ($row['percent_left'] !== null)
                                    <div class="mt-1.5 h-1.5 w-28 rounded-full bg-stone-200"><div class="h-1.5 rounded-full {{ $st[2] }}" style="width: {{ $row['percent_left'] }}%"></div></div>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-stone-700">{{ $lot->expiry_date?->format('H:i d/m/Y') ?? '—' }}<span class="block text-xs {{ $row['state'] === 'expired' ? 'font-semibold text-red-700' : 'text-stone-500' }}">{{ $human($row['hours_left']) }}</span></td>
                            <td class="px-4 py-2.5 text-right font-bold">{{ number_format($lot->remaining_qty) }}<span class="block text-[11px] font-normal text-stone-400">/ {{ number_format($lot->initial_qty) }}</span></td>
                            <td class="px-4 py-2.5 text-right text-stone-700">{{ number_format($row['value']) }} đ</td>
                            <td class="px-4 py-2.5 text-right text-stone-600">{{ $forecast[$lot->product_id] ?? '—' }}</td>
                            @if ($canWrite)
                                <td class="px-4 py-2.5">
                                    <form method="POST" action="{{ route('admin.expiry.destroy', $lot) }}" class="flex items-center gap-1.5"
                                          onsubmit="return confirm('Lập phiếu hủy cho lô #{{ $lot->lot_id }}?')">
                                        @csrf
                                        <input type="number" name="quantity" min="1" max="{{ $lot->remaining_qty }}" value="{{ $lot->remaining_qty }}"
                                               class="w-16 rounded-lg border border-stone-300 px-2 py-1 text-right text-xs focus:border-mint-700 focus:outline-none">
                                        <select name="reason_type" class="rounded-lg border border-stone-300 bg-white px-1.5 py-1 text-xs">
                                            @foreach ($reasons as $r)
                                                <option value="{{ $r }}" @selected($r === ($row['state'] === 'expired' ? 'Hết hạn sử dụng' : 'Khác'))>{{ $r }}</option>
                                            @endforeach
                                        </select>
                                        <button class="rounded-lg bg-red-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-red-700" title="Lập phiếu hủy"><i class="fa-solid fa-trash-can"></i></button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canWrite ? 7 : 6 }}" class="px-4 py-10 text-center text-stone-400">
                            <i class="fa-solid fa-circle-check mb-2 text-2xl text-emerald-500"></i><br>Không có lô nào trong nhóm này.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-xs text-stone-400">Ghi chú: lô quá hạn đã bị khóa khỏi POS/đặt hàng online. Cột "Dự báo SX" giúp cân nhắc giảm sản lượng ngày kế tiếp khi hàng tồn đọng nhiều. Nguyên liệu thô trong kho không có hạn dùng nên không nằm trong trang này.</p>
</div>
@endsection
