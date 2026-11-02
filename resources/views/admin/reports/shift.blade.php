@extends('layouts.system_layout')

@section('title', 'Báo cáo ca làm việc - The Daily Crust')

@section('content')
<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-mint-900"><i class="fa-solid fa-clipboard-user mr-2 text-mint-700"></i>Báo cáo ca làm việc</h1>
            <p class="mt-1 text-sm text-stone-500">Tổng hợp hoạt động của từng nhân viên trong ngày: bán hàng POS, mẻ bánh đã làm, phiếu hủy đã lập.</p>
        </div>
        <form method="GET" id="shiftForm"><input type="text" id="shiftDate" name="date" value="{{ $date->toDateString() }}" readonly></form>
    </div>

    <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm"><div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[#d8e8d2] text-left text-xs font-bold uppercase tracking-wider text-mint-900">
                <tr><th class="px-4 py-3">Nhân viên</th><th class="px-4 py-3">Chức vụ</th><th class="px-4 py-3 text-right">Đơn POS</th><th class="px-4 py-3 text-right">Doanh thu POS</th><th class="px-4 py-3 text-right">Mẻ làm</th><th class="px-4 py-3 text-right">SL đạt</th><th class="px-4 py-3 text-right">SL lỗi</th><th class="px-4 py-3 text-right">Phiếu hủy</th><th class="px-4 py-3 text-right">Giá trị hủy</th></tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($rows as $r)
                    <tr class="hover:bg-stone-50/80">
                        <td class="whitespace-nowrap px-4 py-2.5 font-semibold">{{ $r['name'] }}</td><td class="whitespace-nowrap px-4 py-2.5 text-stone-600">{{ $r['position'] }}</td>
                        <td class="px-4 py-2.5 text-right tnum">{{ $r['orders'] ?: '—' }}</td><td class="whitespace-nowrap px-4 py-2.5 text-right tnum">{{ $r['revenue'] ? number_format($r['revenue']).'đ' : '—' }}</td>
                        <td class="px-4 py-2.5 text-right tnum">{{ $r['batches'] ?: '—' }}</td><td class="px-4 py-2.5 text-right tnum text-emerald-700">{{ $r['passed'] ?: '—' }}</td><td class="px-4 py-2.5 text-right tnum {{ $r['failed'] ? 'text-red-700' : '' }}">{{ $r['failed'] ?: '—' }}</td>
                        <td class="px-4 py-2.5 text-right tnum">{{ $r['tickets'] ?: '—' }}</td><td class="whitespace-nowrap px-4 py-2.5 text-right tnum">{{ $r['cost'] ? number_format($r['cost']).'đ' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-10 text-center text-stone-400">Không có hoạt động nào được ghi nhận trong ngày này.</td></tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot class="border-t border-stone-200 bg-stone-50 text-sm font-bold">
                    <tr><td class="px-4 py-2.5" colspan="2">Tổng</td><td class="px-4 py-2.5 text-right tnum">{{ $rows->sum('orders') }}</td><td class="whitespace-nowrap px-4 py-2.5 text-right tnum">{{ number_format($rows->sum('revenue')) }}đ</td><td class="px-4 py-2.5 text-right tnum">{{ $rows->sum('batches') }}</td><td class="px-4 py-2.5 text-right tnum">{{ $rows->sum('passed') }}</td><td class="px-4 py-2.5 text-right tnum">{{ $rows->sum('failed') }}</td><td class="px-4 py-2.5 text-right tnum">{{ $rows->sum('tickets') }}</td><td class="whitespace-nowrap px-4 py-2.5 text-right tnum">{{ number_format($rows->sum('cost')) }}đ</td></tr>
                </tfoot>
            @endif
        </table></div></div>
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
    flatpickr('#shiftDate', { dateFormat: 'Y-m-d', altInput: true, altFormat: 'd/m/Y', disableMobile: true, maxDate: 'today',
        altInputClass: 'w-40 cursor-pointer rounded-lg border border-stone-300 bg-white px-3 py-1.5 text-sm font-semibold text-stone-800 focus:border-mint-700 focus:outline-none',
        locale: Object.assign({}, (window.flatpickr && flatpickr.l10ns && flatpickr.l10ns.vn) || {}, { firstDayOfWeek: 1 }),
        onChange: (s, str) => { if (str) document.getElementById('shiftForm').submit(); } });
</script>
@endpush
