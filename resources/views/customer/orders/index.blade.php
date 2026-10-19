@extends('layouts.system_layout')

@section('title', 'Đơn hàng của tôi - The Daily Crust')

@section('content')
@php
    $statusLabel = [
        'New' => ['Đã gửi, chờ tiệm nhận', 'bg-sky-100 text-sky-800'],
        'Preparing' => ['Tiệm đang chuẩn bị', 'bg-amber-100 text-amber-800'],
        'Ready' => ['Sẵn sàng', 'bg-emerald-100 text-emerald-800'],
        'Completed' => ['Hoàn tất', 'bg-stone-100 text-stone-600'],
    ];
@endphp
<div class="mx-auto max-w-3xl space-y-5">
    <div>
        <a href="{{ route('home') }}" class="text-sm text-mint-700 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i>Tiếp tục mua sắm</a>
        <h1 class="mt-1 text-2xl font-bold text-mint-900">Đơn hàng của tôi</h1>
    </div>

    @if (session('success'))
        <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"><i class="fa-solid fa-circle-check mr-1"></i>{{ session('success') }}</p>
    @endif

    @forelse ($orders as $o)
        @php $st = $statusLabel[$o->status] ?? [$o->status, 'bg-stone-100 text-stone-600']; @endphp
        <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <span class="font-mono font-bold text-mint-800">#{{ $o->order_id }}</span>
                    <span class="ml-2 text-sm text-stone-500">{{ $o->order_datetime?->format('H:i d/m/Y') }}</span>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $st[1] }}">{{ $st[0] }}</span>
            </div>
            <ul class="mt-3 divide-y divide-stone-100 text-sm">
                @foreach ($o->lineSummary() as $l)
                    <li class="flex justify-between py-1.5"><span>{{ $l['qty'] }} × {{ $l['name'] }}</span><span class="text-stone-500">{{ number_format($l['subtotal'], 0, ',', '.') }} đ</span></li>
                @endforeach
            </ul>
            <div class="mt-2 flex items-center justify-between border-t border-stone-200 pt-2">
                <span class="text-xs text-stone-500">{{ $o->delivery_method === 'Delivery_2km' ? 'Giao tận nơi: '.$o->delivery_address : 'Tự đến lấy tại tiệm' }}</span>
                <span class="font-extrabold text-amber-800">{{ number_format($o->total_amount, 0, ',', '.') }} đ</span>
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-stone-300 bg-white p-10 text-center text-stone-500">
            <i class="fa-solid fa-bag-shopping mb-2 text-3xl text-mint-700/60"></i>
            <p>Bạn chưa có đơn hàng online nào.</p>
        </div>
    @endforelse

    {{ $orders->links() }}
</div>
@endsection

@push('scripts')
@if (session('clear_cart'))
<script>try { localStorage.removeItem('tdc_cart_v1'); } catch (e) {}</script>
@endif
@endpush
