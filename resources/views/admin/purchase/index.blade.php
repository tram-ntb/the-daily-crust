@extends('layouts.system_layout')

@section('title', 'Đơn mua nhà cung cấp - The Daily Crust')

@section('content')
@php
    $tabs = ['all' => 'Tất cả', 'Pending' => 'Chờ nhận hàng', 'Received' => 'Đã nhận', 'Cancelled' => 'Đã hủy'];
    $badge = ['Pending' => ['border-amber-200 bg-amber-50 text-amber-800', 'Chờ nhận hàng'], 'Received' => ['border-emerald-200 bg-emerald-50 text-emerald-800', 'Đã nhận'], 'Cancelled' => ['border-stone-200 bg-stone-100 text-stone-600', 'Đã hủy']];
@endphp
<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-mint-900"><i class="fa-solid fa-cart-flatbed mr-2 text-mint-700"></i>Đơn mua nhà cung cấp</h1>
            <p class="mt-1 text-sm text-stone-500">Lập đơn mua nguyên liệu từ gợi ý thiếu hụt; khi nhận hàng, tồn kho nguyên liệu được cộng tự động.</p>
        </div>
        <a href="{{ route('admin.purchase.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-mint-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-mint-800"><i class="fa-solid fa-plus"></i>Lập phiếu mua nguyên liệu</a>
    </div>

    @include('staff.partials.flash')

    <div class="flex flex-wrap gap-2 border-b border-stone-200">
        @foreach ($tabs as $k => $label)
            <a href="{{ route('admin.purchase.index', ['status' => $k]) }}" class="-mb-px whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold {{ $status === $k ? 'border-mint-700 text-mint-800' : 'border-transparent text-stone-500 hover:text-mint-700' }}">
                {{ $label }}@if ($k !== 'all') <span class="ml-1 text-xs text-stone-400">{{ $counts[$k] ?? 0 }}</span>@endif
            </a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#d8e8d2] text-left text-xs font-bold uppercase tracking-wider text-mint-900">
                    <tr><th class="px-4 py-3">Đơn</th><th class="px-4 py-3">Ngày lập</th><th class="px-4 py-3">Nhà cung cấp</th><th class="px-4 py-3">Dự kiến giao</th><th class="px-4 py-3 text-right">Tổng tiền</th><th class="px-4 py-3">Trạng thái</th><th class="px-4 py-3 text-right">Thao tác</th></tr>
                </thead>
                @forelse ($orders as $o)
                    @php $b = $badge[$o->po_status] ?? ['border-stone-200 bg-stone-50 text-stone-600', $o->po_status ?? '—']; @endphp
                    <tbody x-data="{ open: false }" class="border-t border-stone-100">
                        <tr class="hover:bg-stone-50/80">
                            <td class="px-4 py-2.5 font-mono text-xs">#{{ $o->po_id }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-stone-600">{{ $o->order_date?->format('H:i d/m/Y') }}</td>
                            <td class="px-4 py-2.5 font-medium">{{ $o->supplier->company_name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-stone-600">{{ $o->expected_delivery_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right font-semibold tnum">{{ number_format($o->total_amount) }}đ</td>
                            <td class="whitespace-nowrap px-4 py-2.5"><span class="inline-flex items-center rounded-md border px-2.5 py-1 text-xs font-semibold {{ $b[0] }}">{{ $b[1] }}</span></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                <button type="button" @click="open = !open" class="rounded-md border border-stone-300 px-3 py-1 text-xs font-semibold text-stone-700 hover:bg-stone-50"><i class="fa-solid" :class="open ? 'fa-chevron-up' : 'fa-chevron-down'"></i> Chi tiết</button>
                            </td>
                        </tr>
                        <tr x-show="open" x-cloak>
                            <td colspan="7" class="bg-stone-50/70 px-4 py-3">
                                @if ($o->note)<p class="mb-2 text-xs text-stone-500">Ghi chú: {{ $o->note }}</p>@endif
                                <form method="POST" action="{{ route('admin.purchase.receive', $o) }}">
                                    @csrf
                                    <table class="w-full text-xs">
                                        <thead class="text-left text-stone-500"><tr><th class="py-1">Nguyên liệu</th><th class="py-1 text-right">Đặt</th><th class="py-1 text-right">Đơn giá</th><th class="py-1 text-right">Thành tiền</th><th class="py-1 text-right">Thực nhận</th></tr></thead>
                                        <tbody class="divide-y divide-stone-200/70">
                                            @foreach ($o->details as $d)
                                                <tr>
                                                    <td class="py-1.5">{{ $d->ingredient->ingredient_name ?? '#'.$d->ingredient_id }}</td>
                                                    <td class="whitespace-nowrap py-1.5 text-right tnum">{{ number_format($d->ordered_qty) }} {{ $d->unit }}</td>
                                                    <td class="whitespace-nowrap py-1.5 text-right tnum">{{ number_format($d->unit_price) }}đ</td>
                                                    <td class="whitespace-nowrap py-1.5 text-right tnum">{{ number_format($d->total_price) }}đ</td>
                                                    <td class="py-1.5 text-right">
                                                        @if ($o->po_status === 'Pending')
                                                            <input type="number" min="0" name="received[{{ $d->detail_id }}]" value="{{ $d->ordered_qty }}" class="w-24 rounded-md border border-stone-300 px-2 py-1 text-right tnum focus:border-mint-700 focus:outline-none">
                                                        @else
                                                            <span class="tnum">{{ number_format($d->received_qty ?? 0) }} {{ $d->unit }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    @if ($o->po_status === 'Pending')
                                        <div class="mt-3 flex flex-wrap justify-end gap-2">
                                            <button formaction="{{ route('admin.purchase.cancel', $o) }}" onclick="return confirm('Hủy đơn mua #{{ $o->po_id }}?')" class="rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">Hủy đơn</button>
                                            <button onclick="return confirm('Xác nhận đã nhận hàng và cộng vào kho?')" class="rounded-md bg-mint-700 px-4 py-1.5 text-xs font-semibold text-white hover:bg-mint-800"><i class="fa-solid fa-truck-ramp-box mr-1"></i>Xác nhận đã nhận hàng</button>
                                        </div>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody><tr><td colspan="7" class="px-4 py-10 text-center text-stone-400">Chưa có đơn mua nào.</td></tr></tbody>
                @endforelse
            </table>
        </div>
        <div class="border-t border-stone-100 px-4 py-3">{{ $orders->links() }}</div>
    </div>
</div>
@endsection

@push('styles')
<style>[x-cloak]{display:none !important}</style>
@endpush
