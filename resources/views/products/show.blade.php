@extends('layouts.system_layout')

@section('title', $product->vi_name.' - The Daily Crust')

@section('content')
@php
    $role = Auth::check() ? (Auth::user()->role ?? 'customer') : 'guest';
    $inStock = $stock > 0;
    $imageUrl = $product->image_url && file_exists(public_path($product->image_url)) ? asset($product->image_url) : null;
@endphp
<div class="mx-auto max-w-5xl space-y-6">

    <a href="{{ route('home') }}" class="inline-flex items-center text-sm font-semibold text-mint-700 hover:underline">
        <i class="fa-solid fa-arrow-left mr-1"></i>Quay lại danh sách sản phẩm
    </a>

    {{-- Thông tin chung --}}
    <div class="grid grid-cols-1 gap-6 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm md:grid-cols-2 md:p-6">
        <div class="flex h-72 items-center justify-center overflow-hidden rounded-xl bg-stone-100 md:h-96">
            @if ($imageUrl)
                <img src="{{ $imageUrl }}" alt="{{ $product->vi_name }}" class="h-full w-full object-cover">
            @else
                <div class="flex flex-col items-center text-stone-400">
                    <i class="fa-solid fa-bread-slice mb-2 text-6xl text-stone-300"></i>
                    <span class="text-xs">The Daily Crust</span>
                </div>
            @endif
        </div>

        <div class="flex flex-col">
            @if ($product->category)
                <span class="w-fit rounded-full bg-[#d8e8d2] px-3 py-1 text-xs font-bold uppercase tracking-wider text-mint-800">{{ $product->category }}</span>
            @endif
            <h1 class="mt-3 text-3xl font-extrabold leading-tight text-stone-900">{{ $product->item }}</h1>
            <p class="text-lg font-semibold text-stone-700">{{ $product->vi_name }}</p>
            <p class="mt-3 text-sm leading-relaxed text-stone-600">
                {{ $product->description ?? 'Bánh thủ công thơm ngon từ nguyên liệu tự nhiên.' }}
            </p>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <span class="text-2xl font-extrabold text-amber-800">{{ number_format((int) $product->price, 0, ',', '.') }} đ</span>
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $stock <= 0 ? 'bg-red-100 text-red-700' : ($stock <= 5 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-50 text-emerald-700') }}">
                    {{ $stock <= 0 ? 'Hết hàng' : ($stock <= 5 ? 'Sắp hết: còn '.$stock : 'Còn hàng') }}
                </span>
            </div>

            <div class="mt-auto pt-6">
                @if ($role === 'customer')
                    @if ($inStock)
                        <button type="button"
                                @click="$store.cart.add({ id: {{ $product->product_id }}, name: @js($product->vi_name), sub: @js($product->item), price: {{ (int) $product->price }}, image: @js($imageUrl), stock: {{ $stock }} })"
                                class="inline-flex items-center gap-2 rounded-lg bg-mint-700 px-6 py-3 font-bold text-white shadow-sm hover:bg-mint-800">
                            <i class="fa-solid fa-cart-plus"></i>Thêm vào giỏ hàng
                        </button>
                    @else
                        <button type="button" disabled class="inline-flex cursor-not-allowed items-center gap-2 rounded-lg bg-stone-200 px-6 py-3 font-bold text-stone-500">
                            <i class="fa-solid fa-ban"></i>Hết hàng
                        </button>
                    @endif
                @elseif ($role === 'guest')
                    <button type="button" onclick="alert('Vui lòng đăng nhập để thêm sản phẩm vào giỏ hàng!')"
                            class="inline-flex items-center gap-2 rounded-lg bg-mint-700 px-6 py-3 font-bold text-white shadow-sm hover:bg-mint-800">
                        <i class="fa-solid fa-cart-shopping"></i>Thêm vào giỏ hàng
                    </button>
                @else
                    <p class="rounded-lg bg-stone-50 px-4 py-3 text-sm text-stone-500">Tài khoản nhân viên và quản lý không đặt hàng online. Hãy dùng màn hình POS hoặc trang Công thức (BOM).</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Thành phần: chỉ tên nguyên liệu, KHÔNG có định lượng --}}
    <section class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm md:p-6">
        <h2 class="text-lg font-bold text-mint-900"><i class="fa-solid fa-wheat-awn mr-2 text-mint-700"></i>Thành phần</h2>
        <p class="mt-1 text-sm text-stone-500">Các nguyên liệu có trong sản phẩm này.</p>

        @if ($ingredients->isNotEmpty())
            <ul class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($ingredients as $name)
                    <li class="flex items-center gap-2 rounded-lg border border-stone-100 bg-stone-50 px-3 py-2.5 text-sm font-medium text-stone-800">
                        <i class="fa-solid fa-leaf text-mint-700/70"></i>{{ $name }}
                    </li>
                @endforeach
            </ul>
            <p class="mt-4 text-xs text-stone-400">Liệt kê theo thứ tự chữ cái. Định lượng chi tiết là công thức riêng của tiệm nên không công bố.</p>
        @else
            <p class="mt-4 rounded-lg border border-dashed border-stone-300 p-6 text-center text-sm text-stone-400">Thông tin thành phần của sản phẩm này đang được cập nhật.</p>
        @endif
    </section>

    {{-- Sản phẩm cùng loại --}}
    @if ($related->isNotEmpty())
        <section>
            <h2 class="mb-3 text-lg font-bold text-stone-900">Sản phẩm cùng loại</h2>
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                @foreach ($related as $r)
                    @php $rImg = $r->image_url && file_exists(public_path($r->image_url)) ? asset($r->image_url) : null; @endphp
                    <a href="{{ route('products.show', $r->product_id) }}" class="group overflow-hidden rounded-xl border border-stone-200 bg-white shadow-xs transition hover:shadow-md">
                        <div class="flex h-28 items-center justify-center bg-stone-100">
                            @if ($rImg)
                                <img src="{{ $rImg }}" alt="{{ $r->vi_name }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                            @else
                                <i class="fa-solid fa-bread-slice text-3xl text-stone-300"></i>
                            @endif
                        </div>
                        <div class="p-3">
                            <p class="truncate text-sm font-bold text-stone-900 group-hover:text-mint-700">{{ $r->vi_name }}</p>
                            <p class="text-sm font-semibold text-amber-800">{{ number_format((int) $r->price, 0, ',', '.') }} đ</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>

{{-- Giỏ hàng nổi (chỉ khách hàng đã đăng nhập) để nút "Thêm vào giỏ hàng" ở trên hoạt động --}}
@if ($role === 'customer')
    @include('customer._cart')
@endif
@endsection
