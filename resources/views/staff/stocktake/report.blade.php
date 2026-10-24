@extends('layouts.system_layout')

@section('title', 'Biên bản giải trình hao hụt - The Daily Crust')

@section('content')
@php
    $statusText = ['Pending_Approval' => 'Chờ Quản lý duyệt', 'Completed' => 'Đã duyệt', 'In_Progress' => 'Đang kiểm'][$session->status] ?? $session->status;
@endphp
<div class="mx-auto max-w-5xl space-y-5">
    <div class="print:hidden">
        <a href="{{ route('admin.stocktake.show', $session) }}" class="text-sm text-mint-700 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i>Quay lại phiên kiểm kê</a>
    </div>

    <div class="print:hidden">@include('staff.partials.flash')</div>

    <form method="POST" action="{{ route('admin.stocktake.report.save', $session) }}" class="space-y-5 rounded-xl border border-stone-200 bg-white p-6 shadow-sm print:border-0 print:shadow-none">
        @csrf
        @method('PUT')

        {{-- Đầu biên bản (tự điền) --}}
        <div class="text-center">
            <p class="text-xs font-bold uppercase tracking-widest text-stone-500">Tiệm bánh The Daily Crust</p>
            <h1 class="mt-1 text-xl font-extrabold uppercase text-mint-900">Biên bản giải trình hao hụt kiểm kê</h1>
            <p class="text-sm text-stone-500">Số: KK-{{ str_pad((string) $session->stocktake_id, 4, '0', STR_PAD_LEFT) }} · Ngày kiểm kê: {{ $session->stocktake_date?->format('H:i d/m/Y') }}</p>
        </div>

        <dl class="grid grid-cols-1 gap-x-8 gap-y-1 text-sm sm:grid-cols-2">
            <div class="flex gap-2"><dt class="w-32 font-semibold text-stone-600">Phiên kiểm kê</dt><dd>{{ $session->session_name }}</dd></div>
            <div class="flex gap-2"><dt class="w-32 font-semibold text-stone-600">Người kiểm kê</dt><dd>{{ $session->creator->full_name ?? '—' }}</dd></div>
            <div class="flex gap-2"><dt class="w-32 font-semibold text-stone-600">Trạng thái</dt><dd class="font-semibold">{{ $statusText }}</dd></div>
            <div class="flex gap-2"><dt class="w-32 font-semibold text-stone-600">Người duyệt</dt><dd>{{ $session->approver->full_name ?? '—' }}</dd></div>
        </dl>

        <p class="rounded-lg bg-stone-50 px-4 py-3 text-sm leading-relaxed text-stone-700 print:bg-transparent">
            Qua đối soát giữa số liệu hệ thống và số đếm thực tế, phát hiện <b>{{ $rows->count() }}</b> mặt hàng có chênh lệch
            <b>vượt mức dung sai cho phép</b>, trong đó giá trị hao hụt (thiếu so với hệ thống) ước tính
            <b class="text-red-700">{{ number_format($totalLoss) }} đồng</b>. Bên dưới là nguyên nhân và biện pháp khắc phục do người kiểm kê giải trình.
        </p>

        {{-- Bảng giải trình --}}
        <div class="space-y-4">
            @foreach ($rows as $i => $r)
                @php $d = $r['detail']; $id = $d->stocktake_detail_id; @endphp
                <div class="rounded-lg border border-stone-200 p-4 print:break-inside-avoid">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-bold text-stone-900">{{ $i + 1 }}. {{ $d->label() }}</p>
                            <p class="text-xs text-stone-400">{{ $d->item_type === 'Ingredient' ? 'Nguyên liệu' : 'Thành phẩm' }} · đơn vị: {{ $d->unit }}</p>
                        </div>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $r['variance'] < 0 ? 'bg-red-100 text-red-800' : 'bg-sky-100 text-sky-800' }}">
                            {{ $r['variance'] < 0 ? 'Thiếu' : 'Thừa' }} {{ number_format(abs($r['variance'])) }} ({{ number_format($d->variance_percentage, 1) }}%)
                        </span>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 text-center text-sm md:grid-cols-5">
                        <div class="rounded-lg bg-stone-50 p-2 print:border"><div class="text-[11px] text-stone-500">Số hệ thống</div><div class="font-bold">{{ number_format($d->system_qty) }}</div></div>
                        <div class="rounded-lg bg-stone-50 p-2 print:border"><div class="text-[11px] text-stone-500">Số thực tế</div><div class="font-bold">{{ number_format($d->actual_qty) }}</div></div>
                        <div class="rounded-lg bg-stone-50 p-2 print:border"><div class="text-[11px] text-stone-500">Chênh lệch</div><div class="font-bold {{ $r['variance'] < 0 ? 'text-red-700' : 'text-sky-700' }}">{{ $r['variance'] > 0 ? '+' : '' }}{{ number_format($r['variance']) }}</div></div>
                        <div class="rounded-lg bg-stone-50 p-2 print:border"><div class="text-[11px] text-stone-500">Dung sai cho phép</div><div class="font-bold">{{ rtrim(rtrim(number_format($r['tolerance'], 1), '0'), '.') }}%</div></div>
                        <div class="rounded-lg bg-stone-50 p-2 print:border"><div class="text-[11px] text-stone-500">Giá trị hao hụt</div><div class="font-bold text-red-700">{{ $r['loss_value'] > 0 ? number_format($r['loss_value']).' đ' : '—' }}</div></div>
                    </div>

                    @if ($editable)
                        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3 print:hidden">
                            <label class="block">
                                <span class="text-xs font-semibold text-stone-600">Nguyên nhân <span class="text-red-600">*</span></span>
                                <select name="rows[{{ $id }}][cause]" required class="mt-1 w-full rounded-lg border border-stone-300 bg-white px-2.5 py-2 text-sm focus:border-mint-700 focus:outline-none">
                                    <option value="">-- Chọn nguyên nhân --</option>
                                    @foreach ($causes as $c)
                                        <option value="{{ $c }}" @selected(old("rows.$id.cause", $r['cause']) === $c)>{{ $c }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block md:col-span-2">
                                <span class="text-xs font-semibold text-stone-600">Biện pháp khắc phục <span class="text-red-600">*</span></span>
                                <input type="text" maxlength="90" required name="rows[{{ $id }}][measure]" value="{{ old("rows.$id.measure", $r['measure']) }}" placeholder="VD: hiệu chuẩn lại cân, kiểm đếm lại khi nhập hàng..."
                                       class="mt-1 w-full rounded-lg border border-stone-300 px-2.5 py-2 text-sm focus:border-mint-700 focus:outline-none">
                            </label>
                            <label class="block md:col-span-3">
                                <span class="text-xs font-semibold text-stone-600">Ghi chú thêm (tùy chọn)</span>
                                <input type="text" maxlength="60" name="rows[{{ $id }}][note]" value="{{ old("rows.$id.note", $r['note']) }}"
                                       class="mt-1 w-full rounded-lg border border-stone-300 px-2.5 py-2 text-sm focus:border-mint-700 focus:outline-none">
                            </label>
                        </div>
                    @endif

                    {{-- Nội dung đã lưu (hiển thị khi xem / in) --}}
                    <p class="mt-3 text-sm {{ $editable ? 'hidden print:block' : '' }}">
                        <span class="font-semibold text-stone-600">Giải trình:</span>
                        {{ $r['raw'] !== '' ? $r['raw'] : '(chưa lập biên bản)' }}
                    </p>
                </div>
            @endforeach
        </div>

        {{-- Chữ ký --}}
        <div class="grid grid-cols-2 gap-6 pt-4 text-center text-sm">
            <div><p class="font-semibold text-stone-700">Người lập biên bản</p><p class="text-xs text-stone-400">(Ký, ghi rõ họ tên)</p><p class="mt-12 font-semibold">{{ $session->creator->full_name ?? $author->full_name }}</p></div>
            <div><p class="font-semibold text-stone-700">Quản lý xác nhận</p><p class="text-xs text-stone-400">(Ký, ghi rõ họ tên)</p><p class="mt-12 font-semibold">{{ $session->approver->full_name ?? '' }}</p></div>
        </div>

        <div class="flex flex-wrap justify-end gap-2 print:hidden">
            <button type="button" onclick="window.print()" class="rounded-lg border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-50"><i class="fa-solid fa-print mr-1"></i>In biên bản</button>
            @if ($editable)
                <button class="rounded-lg bg-mint-700 px-5 py-2 text-sm font-semibold text-white hover:bg-mint-800"><i class="fa-solid fa-floppy-disk mr-1"></i>Lưu biên bản</button>
            @endif
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>
    @media print {
        aside, header, nav { display: none !important; }
        body { background: #fff !important; }
    }
</style>
@endpush
