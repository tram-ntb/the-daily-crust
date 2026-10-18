@extends('layouts.system_layout')

@section('title', 'Quản lý Kho & Danh mục - The Daily Crust')

@section('content')
<div class="space-y-6">
    <!-- Tiêu đề phân hệ -->
    <div class="bg-white p-6 rounded-2xl border border-stone-200 shadow-xs flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-black text-stone-900">Quản lý Kho & Danh mục Nguyên liệu</h1>
            <p class="text-sm text-stone-500 mt-1">Theo dõi tồn kho nguyên liệu thô, sản phẩm và cảnh báo mức tồn an toàn.</p>
        </div>
    </div>

    <!-- KHỐI DANH MỤC & CẢNH BÁO TỒN KHO TỐI THIỂU -->
    <div class="bg-white rounded-2xl border border-stone-200 p-6 shadow-xs">
        <h2 class="text-lg font-bold text-stone-900 mb-4"><i class="fa-solid fa-boxes-stacked text-amber-700 mr-2"></i> Danh mục nguyên liệu & Cảnh báo tồn kho an toàn</h2>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-stone-100 text-stone-700 text-xs uppercase font-bold">
                        <th class="p-3">Tên Nguyên liệu</th>
                        <th class="p-3">Danh mục</th>
                        <th class="p-3">Tồn kho hiện tại</th>
                        <th class="p-3">Định mức tối thiểu</th>
                        <th class="p-3">Trạng thái cảnh báo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200 text-sm">
                    @isset($ingredients)
                        @foreach($ingredients as $ing)
                        @php
                            $isLowStock = $ing->stock_quantity <= $ing->min_stock_level;
                        @endphp
                        <tr class="{{ $isLowStock ? 'bg-rose-50/50' : '' }}">
                            <td class="p-3 font-semibold text-stone-800">{{ $ing->ingredient_name }}</td>
                            <td class="p-3 text-stone-600">{{ $ing->category }}</td>
                            <td class="p-3 font-bold {{ $isLowStock ? 'text-rose-600' : 'text-stone-900' }}">
                                {{ number_format($ing->stock_quantity, 0) }} {{ $ing->unit }}
                            </td>
                            <td class="p-3 text-stone-500">{{ number_format($ing->min_stock_level, 0) }} {{ $ing->unit }}</td>
                            <td class="p-3">
                                @if($isLowStock)
                                    <span class="px-2 py-1 bg-rose-100 text-rose-800 rounded-md text-xs font-bold inline-flex items-center">
                                        <i class="fa-solid fa-triangle-exclamation mr-1"></i> Sắp hết / Dưới định mức
                                    </span>
                                @else
                                    <span class="px-2 py-1 bg-emerald-100 text-emerald-800 rounded-md text-xs font-bold">Ổn định</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    @endisset
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-2xl border border-stone-200 bg-white p-6 shadow-xs">
            <h2 class="mb-4 text-lg font-bold text-stone-900"><i class="fa-solid fa-bread-slice mr-2 text-mint-700"></i>Danh mục sản phẩm</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-stone-100 text-xs uppercase text-stone-700">
                        <tr><th class="p-3">Sản phẩm</th><th class="p-3">Danh mục</th><th class="p-3">Trạng thái</th></tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200">
                        @forelse($products as $product)
                            <tr><td class="p-3 font-semibold">{{ $product->vi_name }}</td><td class="p-3">{{ $product->category }}</td><td class="p-3">{{ $product->status }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="p-3 text-center text-stone-500">Chưa có sản phẩm.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-stone-200 bg-white p-6 shadow-xs">
            <h2 class="mb-4 text-lg font-bold text-stone-900"><i class="fa-solid fa-truck-field mr-2 text-mint-700"></i>Danh mục nhà cung cấp</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-stone-100 text-xs uppercase text-stone-700">
                        <tr><th class="p-3">Nhà cung cấp</th><th class="p-3">Liên hệ</th><th class="p-3">Trạng thái</th></tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200">
                        @forelse($suppliers as $supplier)
                            <tr><td class="p-3 font-semibold">{{ $supplier->company_name }}</td><td class="p-3">{{ $supplier->contact_name ?? $supplier->phone }}</td><td class="p-3">{{ $supplier->status }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="p-3 text-center text-stone-500">Chưa có nhà cung cấp.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection