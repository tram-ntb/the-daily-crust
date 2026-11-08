@extends('layouts.system_layout')

@section('title', $product->vi_name.' - The Daily Crust')

@section('content')
@php
    $role = Auth::check() ? (Auth::user()->role ?? 'customer') : 'guest';
    $inStock = $stock > 0;
    $imageUrl = $product->image_url && file_exists(public_path($product->image_url)) ? asset($product->image_url) : null;
@endphp
<div class="mx-auto max-w-7xl space-y-4">

    <a href="{{ route('home') }}" class="inline-flex items-center text-sm font-semibold text-mint-700 hover:underline">
        <i class="fa-solid fa-arrow-left mr-1"></i>Quay lại danh sách sản phẩm
    </a>

    {{-- Bố cục 3 khối: trái (Thông tin + Thành phần, xếp chồng) | phải (Sản phẩm cùng loại, cột dọc) --}}
    <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-3">

        <div class="space-y-5 lg:col-span-2">

            {{-- Khối 1: Thông tin chi tiết --}}
            <div class="grid grid-cols-1 gap-5 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm md:grid-cols-2">
                <div class="flex h-64 items-center justify-center overflow-hidden rounded-xl bg-stone-100 md:h-full md:min-h-[17rem]">
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
                    <h1 class="mt-3 text-2xl font-extrabold leading-tight text-stone-900 lg:text-3xl">{{ $product->item }}</h1>
                    <p class="text-base font-semibold text-stone-700">{{ $product->vi_name }}</p>
                    <p class="mt-3 text-sm leading-relaxed text-stone-600">
                        {{ $product->description ?? 'Bánh thủ công thơm ngon từ nguyên liệu tự nhiên.' }}
                    </p>

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <span class="text-2xl font-extrabold text-amber-800">{{ number_format((int) $product->price, 0, ',', '.') }} đ</span>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $stock <= 0 ? 'bg-red-100 text-red-700' : ($stock <= 5 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-50 text-emerald-700') }}">
                            {{ $stock <= 0 ? 'Hết hàng' : ($stock <= 5 ? 'Sắp hết: còn '.$stock : 'Còn hàng') }}
                        </span>
                    </div>

                    <div class="mt-auto pt-5">
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

            {{-- Khối 2: Thành phần (chỉ tên nguyên liệu, KHÔNG có định lượng) --}}
            <section class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-mint-900"><i class="fa-solid fa-wheat-awn mr-2 text-mint-700"></i>Thành phần</h2>
                <p class="mt-1 text-sm text-stone-500">Các nguyên liệu có trong sản phẩm này.</p>

                @if ($ingredients->isNotEmpty())
                    <ul class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
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
        </div>

        {{-- Khối 3: Sản phẩm cùng loại (cột dọc bên phải) --}}
        <aside class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm lg:sticky lg:top-4">
            <h2 class="text-lg font-bold text-stone-900"><i class="fa-solid fa-layer-group mr-2 text-mint-700"></i>Sản phẩm cùng loại</h2>

            @if ($related->isNotEmpty())
                <div class="mt-4 flex flex-col gap-3">
                    @foreach ($related as $r)
                        @php $rImg = $r->image_url && file_exists(public_path($r->image_url)) ? asset($r->image_url) : null; @endphp
                        <a href="{{ route('products.show', $r->product_id) }}" class="group flex items-center gap-3 overflow-hidden rounded-xl border border-stone-200 bg-white p-2 transition hover:border-mint-700/40 hover:shadow-md">
                            <div class="flex h-20 w-24 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-stone-100">
                                @if ($rImg)
                                    <img src="{{ $rImg }}" alt="{{ $r->vi_name }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                                @else
                                    <i class="fa-solid fa-bread-slice text-2xl text-stone-300"></i>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="line-clamp-2 text-sm font-bold leading-snug text-stone-900 group-hover:text-mint-700">{{ $r->vi_name }}</p>
                                <p class="mt-1 text-sm font-semibold text-amber-800">{{ number_format((int) $r->price, 0, ',', '.') }} đ</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <p class="mt-4 rounded-lg border border-dashed border-stone-300 p-6 text-center text-sm text-stone-400">Chưa có sản phẩm cùng loại.</p>
            @endif
        </aside>
    </div>
</div>

{{-- Giỏ hàng nổi (chỉ khách hàng đã đăng nhập) để nút "Thêm vào giỏ hàng" ở trên hoạt động --}}
@if ($role === 'customer')
    @include('customer._cart')
@endif
@endsection
