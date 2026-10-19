@extends('layouts.system_layout')

@section('title', 'Phiếu hủy hàng hỏng - The Daily Crust')

@section('content')
<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-mint-900">Phiếu hủy hàng hỏng</h1>
            <p class="mt-1 text-sm text-stone-500">Lập phiếu khi thành phẩm hết hạn/hỏng hoặc nguyên liệu hư; kho tự trừ tương ứng.</p>
        </div>
        <div class="rounded-lg border border-stone-200 bg-white px-4 py-2 text-sm shadow-sm">Giá trị hủy tháng này: <b class="text-red-700">{{ number_format($monthCost) }}đ</b></div>
    </div>

    @include('staff.partials.flash')

    @if ($canWrite)
    <form method="POST" action="{{ route('admin.waste.store') }}" x-data="{ type: '{{ old('item_type', 'Product') }}' }"
          class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
        @csrf
        <h2 class="mb-3 font-semibold text-mint-900">Lập phiếu hủy mới</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <label class="block">
                <span class="text-sm font-semibold text-stone-700">Loại hàng</span>
                <select name="item_type" x-model="type" class="mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2">
                    <option value="Product">Thành phẩm (theo lô)</option>
                    <option value="Ingredient">Nguyên liệu</option>
                </select>
            </label>

            <label class="block md:col-span-2" x-show="type === 'Product'">
                <span class="text-sm font-semibold text-stone-700">Lô thành phẩm</span>
                <select name="lot_id" :disabled="type !== 'Product'" class="mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2">
                    <option value="">-- Chọn lô --</option>
                    @foreach ($lots as $lot)
                        <option value="{{ $lot->lot_id }}" @selected(old('lot_id') == $lot->lot_id)>
                            {{ $lot->product->vi_name ?? 'SP #'.$lot->product_id }} · Lô #{{ $lot->lot_id }} · còn {{ $lot->remaining_qty }} · HSD {{ $lot->expiry_date?->format('H:i d/m') }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="block md:col-span-2" x-show="type === 'Ingredient'" x-cloak>
                <span class="text-sm font-semibold text-stone-700">Nguyên liệu</span>
                <select name="ingredient_id" :disabled="type !== 'Ingredient'" class="mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2">
                    <option value="">-- Chọn nguyên liệu --</option>
                    @foreach ($ingredients as $ing)
                        <option value="{{ $ing->ingredient_id }}" @selected(old('ingredient_id') == $ing->ingredient_id)>{{ $ing->ingredient_name }} (tồn {{ number_format($ing->stock_quantity) }} {{ $ing->unit }})</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="text-sm font-semibold text-stone-700">Số lượng</span>
                <input type="number" name="quantity" min="1" value="{{ old('quantity') }}" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2">
            </label>

            <label class="block md:col-span-2">
                <span class="text-sm font-semibold text-stone-700">Lý do</span>
                <select name="reason_type" class="mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2">
                    @foreach ($reasons as $r)
                        <option value="{{ $r }}" @selected(old('reason_type') === $r)>{{ $r }}</option>
                    @endforeach
                </select>
            </label>

            <div class="flex items-end md:col-span-2 md:justify-end">
                <button class="rounded-lg bg-mint-700 px-5 py-2 text-sm font-semibold text-white hover:bg-mint-800"><i class="fa-solid fa-trash-can mr-1"></i>Lập phiếu hủy</button>
            </div>
        </div>
    </form>
    @endif

    <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#d8e8d2] text-left text-xs font-bold uppercase tracking-wider text-mint-900">
                    <tr><th class="px-4 py-3">Phiếu</th><th class="px-4 py-3">Ngày</th><th class="px-4 py-3">Mặt hàng</th><th class="px-4 py-3 text-right">Số lượng</th><th class="px-4 py-3">Lý do</th><th class="px-4 py-3 text-right">Giá trị</th><th class="px-4 py-3">Người lập</th><th class="px-4 py-3">Duyệt</th></tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($tickets as $t)
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs">#{{ $t->ticket_id }}</td>
                            <td class="px-4 py-2 text-stone-600">{{ $t->ticket_date?->format('H:i d/m/Y') }}</td>
                            <td class="px-4 py-2 font-medium">{{ $t->itemName() }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($t->quantity) }} {{ $t->unit }}</td>
                            <td class="px-4 py-2 text-stone-600">{{ $t->reason_type }}</td>
                            <td class="px-4 py-2 text-right font-semibold text-red-700">{{ number_format($t->total_cost) }}đ</td>
                            <td class="px-4 py-2 text-stone-600">{{ $t->user->full_name ?? '—' }}</td>
                            <td class="px-4 py-2"><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $t->approved_by ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $t->approved_by ? 'Đã duyệt' : 'Chờ duyệt' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-stone-400">Chưa có phiếu hủy.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-stone-100 px-4 py-3">{{ $tickets->links() }}</div>
    </div>
</div>
@endsection

@push('styles')
<style>[x-cloak]{display:none !important}</style>
@endpush
