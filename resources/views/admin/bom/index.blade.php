@extends('layouts.system_layout')

@section('title', 'Công thức & Định lượng BOM - The Daily Crust')

@section('content')
<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-mint-900"><i class="fa-solid fa-book-open mr-2 text-mint-700"></i>Công thức &amp; Định lượng (BOM)</h1>
            <p class="mt-1 text-sm text-stone-500">Mỗi sản phẩm cần những nguyên liệu thô nào, bao nhiêu cho 1 sản phẩm. {{ $canEdit ? 'Bấm vào sản phẩm để xem hoặc chỉnh công thức.' : 'Bấm vào sản phẩm để xem chi tiết (chỉ Quản lý được sửa).' }}</p>
        </div>
    </div>

    @include('staff.partials.flash')

    {{-- Thẻ tổng quan --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-stone-500">Sản phẩm đang bán</p>
            <p class="mt-1 text-2xl font-extrabold text-stone-800">{{ $totalProducts }}</p>
        </div>
        <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-stone-500">Đã có công thức</p>
            <p class="mt-1 text-2xl font-extrabold text-mint-800">{{ $withBom }}</p>
        </div>
        <a href="{{ route('admin.bom.index', ['filter' => 'missing']) }}"
           class="rounded-xl border p-4 shadow-sm transition {{ $totalProducts - $withBom > 0 ? 'border-red-200 bg-red-50 hover:bg-red-100' : 'border-stone-200 bg-white' }}">
            <p class="text-xs font-bold uppercase tracking-wider {{ $totalProducts - $withBom > 0 ? 'text-red-700' : 'text-stone-500' }}">Chưa có công thức</p>
            <p class="mt-1 text-2xl font-extrabold {{ $totalProducts - $withBom > 0 ? 'text-red-800' : 'text-stone-800' }}">{{ $totalProducts - $withBom }}</p>
        </a>
    </div>

    {{-- Bộ lọc --}}
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <div class="relative w-full max-w-xs">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-stone-400"></i>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Tìm sản phẩm..."
                   class="w-full rounded-lg border border-stone-300 bg-white py-2 pl-10 pr-3 text-sm focus:border-mint-700 focus:outline-none">
        </div>
        <select name="category" onchange="this.form.submit()" class="rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm">
            <option value="">Tất cả danh mục</option>
            @foreach ($categories as $c)
                <option value="{{ $c }}" @selected($filters['category'] === $c)>{{ $c }}</option>
            @endforeach
        </select>
        <label class="inline-flex items-center gap-2 rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm">
            <input type="checkbox" name="filter" value="missing" @checked($filters['filter'] === 'missing') onchange="this.form.submit()" class="rounded border-stone-300 text-mint-700">
            Chỉ sản phẩm chưa có BOM
        </label>
        <button class="rounded-lg bg-mint-700 px-4 py-2 text-sm font-semibold text-white hover:bg-mint-800">Lọc</button>
        @if ($filters['q'] || $filters['category'] || $filters['filter'])
            <a href="{{ route('admin.bom.index') }}" class="text-sm font-semibold text-stone-500 hover:underline">Xóa lọc</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#d8e8d2] text-left text-xs font-bold uppercase tracking-wider text-mint-900">
                    <tr>
                        <th class="px-4 py-3">Sản phẩm</th>
                        <th class="px-4 py-3">Danh mục</th>
                        <th class="px-4 py-3 text-right">Số nguyên liệu</th>
                        <th class="px-4 py-3 text-right">Giá vốn / 1 SP</th>
                        <th class="px-4 py-3 text-right">Giá bán</th>
                        <th class="px-4 py-3 text-right">Biên lợi nhuận</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($products as $p)
                        @php
                            $cost = $p->unitCost();
                            $margin = $p->price > 0 && $cost > 0 ? round(($p->price - $cost) / $p->price * 100, 1) : null;
                        @endphp
                        <tr class="hover:bg-stone-50/70">
                            <td class="px-4 py-2.5">
                                <a href="{{ route('admin.bom.show', $p) }}" class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 flex-none items-center justify-center overflow-hidden rounded-lg bg-stone-100">
                                        @if ($p->image_url && file_exists(public_path($p->image_url)))
                                            <img src="{{ asset($p->image_url) }}" alt="" class="h-full w-full object-cover">
                                        @else
                                            <i class="fa-solid fa-bread-slice text-stone-300"></i>
                                        @endif
                                    </span>
                                    <span>
                                        <span class="block font-bold text-stone-900 hover:text-mint-700">{{ $p->vi_name }}</span>
                                        <span class="block text-xs text-stone-400">{{ $p->item }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="px-4 py-2.5 text-stone-600">{{ $p->category ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right">
                                @if ($p->boms_count > 0)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">{{ $p->boms_count }} nguyên liệu</span>
                                @else
                                    <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800">Chưa có BOM</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right text-stone-700">{{ $cost > 0 ? number_format($cost).' đ' : '—' }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold text-amber-800">{{ number_format($p->price) }} đ</td>
                            <td class="px-4 py-2.5 text-right font-semibold {{ $margin === null ? 'text-stone-400' : ($margin < 30 ? 'text-red-700' : 'text-emerald-700') }}">{{ $margin === null ? '—' : $margin.'%' }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <a href="{{ route('admin.bom.show', $p) }}" class="text-sm font-semibold text-mint-700 hover:underline">{{ $canEdit ? ($p->boms_count ? 'Xem / Sửa' : 'Tạo công thức') : 'Xem' }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-stone-400">Không có sản phẩm phù hợp.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-stone-100 px-4 py-3">{{ $products->links() }}</div>
    </div>
</div>
@endsection
