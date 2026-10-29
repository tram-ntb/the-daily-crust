@extends('layouts.system_layout')

@section('title', 'Khách hàng '.$customer->full_name.' - The Daily Crust')
@section('hide_shop_bar', '1')

@section('content')
@php
    $badge = ['New' => 'bg-sky-100 text-sky-800', 'Preparing' => 'bg-amber-100 text-amber-800', 'Ready' => 'bg-emerald-100 text-emerald-800', 'Completed' => 'bg-stone-100 text-stone-600'];
@endphp
<div class="mx-auto max-w-4xl space-y-5">
    <div>
        <a href="{{ route('admin.customers.index') }}" class="text-sm text-mint-700 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i>Danh sách khách hàng</a>
        <h1 class="mt-1 text-2xl font-bold text-mint-900">{{ $customer->full_name }}</h1>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm sm:col-span-2">
            <h2 class="mb-3 font-bold text-mint-900">Thông tin tài khoản <span class="ml-1 rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-500">chỉ xem</span></h2>
            <dl class="grid grid-cols-3 gap-y-2 text-sm">
                <dt class="text-stone-500">Tên đăng nhập</dt><dd class="col-span-2 font-mono">{{ $customer->username }}</dd>
                <dt class="text-stone-500">Họ và tên</dt><dd class="col-span-2">{{ $customer->full_name }}</dd>
                <dt class="text-stone-500">Email</dt><dd class="col-span-2">{{ $customer->email }}</dd>
                <dt class="text-stone-500">Số điện thoại</dt><dd class="col-span-2">{{ $customer->phoneText() ?? 'Chưa có' }}</dd>
                <dt class="text-stone-500">Hạng thành viên</dt><dd class="col-span-2">{{ $customer->position }}</dd>
                <dt class="text-stone-500">Trạng thái</dt><dd class="col-span-2">{{ $customer->isLocked() ? 'Đã khóa' : 'Hoạt động' }}</dd>
            </dl>
        </div>
        <div class="space-y-3">
            <div class="rounded-xl border border-stone-200 bg-white p-4 text-center shadow-sm">
                <p class="text-3xl font-extrabold text-mint-800">{{ $orderCount }}</p><p class="text-xs text-stone-500">Đơn online đã đặt</p>
            </div>
            <div class="rounded-xl border border-stone-200 bg-white p-4 text-center shadow-sm">
                <p class="text-2xl font-extrabold text-amber-800">{{ number_format($orderTotal, 0, ',', '.') }} đ</p><p class="text-xs text-stone-500">Tổng giá trị đơn</p>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 font-bold text-mint-900">Đơn hàng gần đây{{ $orderCount > $orders->count() ? ' (10 đơn mới nhất)' : '' }}</h2>
        @forelse ($orders as $o)
            <div class="border-t border-stone-100 py-3 first:border-0 first:pt-0">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div><span class="font-mono font-bold text-mint-800">#{{ $o->order_id }}</span> <span class="ml-2 text-sm text-stone-500">{{ $o->order_datetime?->format('H:i d/m/Y') }}</span></div>
                    <span class="rounded-full px-3 py-0.5 text-xs font-semibold {{ $badge[$o->status] ?? 'bg-stone-100 text-stone-600' }}">{{ $o->customerStatusLabel() }}</span>
                </div>
                <p class="mt-1 text-sm text-stone-600">{{ $o->lineSummary()->map(fn ($l) => $l['qty'].' × '.$l['name'])->implode(', ') }}</p>
                <p class="mt-0.5 text-xs text-stone-500">{{ number_format($o->total_amount, 0, ',', '.') }} đ · {{ $o->isDelivery() ? 'Giao tận nơi' : 'Nhận tại tiệm' }}</p>
            </div>
        @empty
            <p class="text-sm text-stone-500">Khách này chưa đặt đơn online nào.</p>
        @endforelse
    </div>
</div>
@endsection
