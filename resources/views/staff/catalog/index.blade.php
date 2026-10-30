@extends('layouts.system_layout')

@section('title', 'Danh mục kho - The Daily Crust')

@section('content')
@php
    $tabs = [
        'ingredients' => ['admin.ingredients.index', 'fa-cubes', 'Nguyên liệu'],
        'products'    => ['admin.products.stock', 'fa-bread-slice', 'Thành phẩm'],
        'suppliers'   => ['admin.suppliers.index', 'fa-truck-field', 'Nhà cung cấp'],
    ];
    $badge = [
        'critical' => ['bg-red-100 text-red-800 border-red-200', 'Hết / sắp hết'],
        'low'      => ['bg-amber-100 text-amber-800 border-amber-200', 'Dưới mức tối thiểu'],
        'ok'       => ['bg-emerald-50 text-emerald-800 border-emerald-200', 'Đủ hàng'],
    ];
    $rowTint = ['critical' => 'bg-red-50/60', 'low' => 'bg-amber-50/70', 'ok' => ''];
@endphp
<div class="space-y-5">
    <div>
        <h1 class="text-2xl font-bold text-mint-900">Danh mục kho</h1>
        <p class="mt-1 text-sm text-stone-500">Theo dõi tồn kho và cảnh báo khi tồn thấp hơn mức tối thiểu.</p>
    </div>

    @include('staff.partials.flash')

    {{-- Tab --}}
    <div class="flex flex-wrap gap-2 border-b border-stone-200">
        @foreach ($tabs as $key => [$route, $icon, $label])
            <a href="{{ route($route) }}"
               class="-mb-px inline-flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-semibold {{ $tab === $key ? 'border-mint-700 text-mint-800' : 'border-transparent text-stone-500 hover:text-mint-700' }}">
                <i class="fa-solid {{ $icon }}"></i> {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Bộ lọc + thẻ cảnh báo --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Tìm theo tên..."
                   class="w-56 rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-mint-700 focus:outline-none">
            @if ($categories->isNotEmpty())
                <select name="category" class="rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm">
                    <option value="">Tất cả nhóm</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c }}" @selected($filters['category'] === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            @endif
            @if ($tab === 'ingredients')
                <select name="level" class="rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm">
                    <option value="">Mọi mức tồn</option>
                    <option value="critical" @selected($filters['level'] === 'critical')>Hết / sắp hết (đỏ)</option>
                    <option value="low" @selected($filters['level'] === 'low')>Dưới mức tối thiểu (vàng)</option>
                    <option value="ok" @selected($filters['level'] === 'ok')>Đủ hàng</option>
                </select>
            @endif
            <button class="rounded-lg bg-mint-700 px-4 py-2 text-sm font-semibold text-white hover:bg-mint-800">Lọc</button>
        </form>

        @if ($tab !== 'suppliers')
            <div class="flex gap-2 text-xs font-semibold">
                <span class="rounded-full border border-red-200 bg-red-100 px-3 py-1 text-red-800"><i class="fa-solid fa-circle-exclamation"></i> {{ $counts['critical'] }} đỏ</span>
                <span class="rounded-full border border-amber-200 bg-amber-100 px-3 py-1 text-amber-800"><i class="fa-solid fa-triangle-exclamation"></i> {{ $counts['low'] }} vàng</span>
                <span class="rounded-full border border-stone-200 bg-white px-3 py-1 text-stone-600">{{ $counts['total'] }} mục</span>
            </div>
        @endif
    </div>

    @if ($tab === 'products' && isset($expired) && $expired['qty'] > 0)
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-900">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span><b>{{ number_format($expired['qty']) }} cái</b> của {{ $expired['products'] }} sản phẩm đã <b>hết hạn dùng</b>: không tính vào tồn bán được, khách và POS không mua được.</span>
            @if (\Illuminate\Support\Facades\Route::has('admin.expiry.index'))
                <a href="{{ route('admin.expiry.index') }}" class="ml-auto font-semibold underline">Xử lý ở Hàng cận date &amp; Phiếu hủy</a>
            @endif
        </div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-[#d8e8d2] text-left text-xs font-bold uppercase tracking-wider text-mint-900">
            @if ($tab === 'ingredients')
                <tr><th class="px-4 py-3">Nguyên liệu</th><th class="px-4 py-3">Nhóm</th><th class="px-4 py-3 text-right">Tồn kho</th><th class="px-4 py-3 text-right">Tối thiểu</th><th class="px-4 py-3">Mức tồn</th><th class="px-4 py-3 text-right">Giá vốn / đơn vị</th></tr>
            @elseif ($tab === 'products')
                <tr><th class="px-4 py-3">Sản phẩm</th><th class="px-4 py-3">Nhóm</th><th class="px-4 py-3 text-right">Tồn bán được (lô còn hạn)</th><th class="px-4 py-3">Hạn gần nhất</th><th class="px-4 py-3">Mức tồn</th><th class="px-4 py-3 text-right">Giá bán</th></tr>
            @else
                <tr><th class="px-4 py-3">Mã</th><th class="px-4 py-3">Công ty</th><th class="px-4 py-3">Liên hệ</th><th class="px-4 py-3">Điện thoại</th><th class="px-4 py-3">Giao hàng</th><th class="px-4 py-3">Trạng thái</th></tr>
            @endif
            </thead>
            <tbody class="divide-y divide-stone-100">
            @forelse ($items as $it)
                @if ($tab === 'ingredients')
                    @php $lv = $it->stockLevel(); @endphp
                    <tr class="{{ $rowTint[$lv] }}">
                        <td class="px-4 py-2.5 font-medium text-stone-800">{{ $it->ingredient_name }}</td>
                        <td class="px-4 py-2.5 text-stone-500">{{ $it->category }}</td>
                        <td class="px-4 py-2.5 text-right font-bold {{ $lv === 'critical' ? 'text-red-700' : ($lv === 'low' ? 'text-amber-700' : 'text-stone-800') }}">{{ number_format($it->stock_quantity) }} <span class="text-xs font-normal text-stone-400">{{ $it->unit }}</span></td>
                        <td class="px-4 py-2.5 text-right text-stone-500">{{ number_format($it->min_stock_level) }}</td>
                        <td class="px-4 py-2.5"><span class="rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $badge[$lv][0] }}">{{ $badge[$lv][1] }}</span></td>
                        <td class="px-4 py-2.5 text-right text-stone-500">{{ number_format($it->cost_price_vn) }}đ</td>
                    </tr>
                @elseif ($tab === 'products')
                    @php $lv = $it->stock_level; @endphp
                    <tr class="{{ $rowTint[$lv] }}">
                        <td class="px-4 py-2.5 font-medium text-stone-800">{{ $it->vi_name }}</td>
                        <td class="px-4 py-2.5 text-stone-500">{{ $it->category }}</td>
                        <td class="px-4 py-2.5 text-right font-bold {{ $lv === 'critical' ? 'text-red-700' : ($lv === 'low' ? 'text-amber-700' : 'text-stone-800') }}">
                            {{ number_format((int) $it->stock_qty) }} <span class="text-xs font-normal text-stone-400">cái</span>
                            @if ((int) $it->expired_qty > 0)
                                <a href="{{ \Illuminate\Support\Facades\Route::has('admin.expiry.index') ? route('admin.expiry.index') : '#' }}" class="mt-0.5 block text-[11px] font-semibold text-red-700 hover:underline" title="Lô đã hết hạn dùng, không bán được. Bấm để lập phiếu hủy.">+ {{ number_format((int) $it->expired_qty) }} cái hết hạn</a>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-stone-500">{{ $it->nearest_expiry ? \Illuminate\Support\Carbon::parse($it->nearest_expiry)->format('H:i d/m/Y') : '—' }}</td>
                        <td class="px-4 py-2.5"><span class="rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $badge[$lv][0] }}">{{ $lv === 'critical' ? 'Hết hàng' : ($lv === 'low' ? 'Sắp hết (≤ '.$productLow.')' : 'Đủ hàng') }}</span></td>
                        <td class="px-4 py-2.5 text-right text-stone-500">{{ number_format($it->price) }}đ</td>
                    </tr>
                @else
                    <tr>
                        <td class="px-4 py-2.5 font-mono text-xs text-stone-500">{{ $it->supplier_code }}</td>
                        <td class="px-4 py-2.5 font-medium text-stone-800">{{ $it->company_name }}<div class="text-xs font-normal text-stone-400">{{ $it->address }}</div></td>
                        <td class="px-4 py-2.5">{{ $it->contact_name }}<div class="text-xs text-stone-400">{{ $it->email }}</div></td>
                        <td class="px-4 py-2.5 text-stone-600">{{ $it->phone ? '0'.ltrim((string) $it->phone, '0') : '—' }}</td>
                        <td class="px-4 py-2.5 text-stone-600">{{ $it->lead_time_days }} ngày</td>
                        <td class="px-4 py-2.5"><span class="rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $it->status === 'Active' ? $badge['ok'][0] : 'border-stone-200 bg-stone-100 text-stone-600' }}">{{ $it->status }}</span></td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-stone-400">Không có dữ liệu phù hợp.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
