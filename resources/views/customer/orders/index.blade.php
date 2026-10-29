@extends('layouts.system_layout')

@section('title', 'Đơn hàng của tôi - The Daily Crust')
@section('hide_shop_bar', '1')

@section('content')
@php
    // Tên trạng thái lấy từ Order::customerStatusLabel() (Ready = "Đang trên đường giao" hoặc "Đã chuẩn bị xong" tùy cách nhận hàng)
    $statusStyle = [
        'New' => 'bg-sky-100 text-sky-800',
        'Preparing' => 'bg-amber-100 text-amber-800',
        'Ready' => 'bg-emerald-100 text-emerald-800',
        'Completed' => 'bg-stone-100 text-stone-600',
    ];
    $steps = ['New', 'Preparing', 'Ready', 'Completed'];
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
        @php
            $stIdx = array_search($o->status, $steps, true);
            $eta = $o->status !== 'Completed' ? $o->etaLabel() : null;
        @endphp
        <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <span class="font-mono font-bold text-mint-800">#{{ $o->order_id }}</span>
                    <span class="ml-2 text-sm text-stone-500">{{ $o->order_datetime?->format('H:i d/m/Y') }}</span>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusStyle[$o->status] ?? 'bg-stone-100 text-stone-600' }}">{{ $o->customerStatusLabel() }}</span>
            </div>

            {{-- Tiến trình đơn: Đã gửi → Đóng gói → Chuẩn bị xong / Đang giao → Hoàn tất --}}
            <div class="mt-3 flex items-center gap-1" aria-hidden="true">
                @foreach ($steps as $i => $step)
                    <span class="h-1.5 flex-1 rounded-full {{ $stIdx !== false && $i <= $stIdx ? 'bg-mint-600' : 'bg-stone-200' }}"></span>
                @endforeach
            </div>
            @if ($eta)
                <p class="mt-2 text-sm font-semibold text-mint-800"><i class="fa-regular fa-clock mr-1"></i>{{ $eta }}</p>
            @endif
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
