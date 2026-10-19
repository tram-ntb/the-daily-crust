@extends('layouts.system_layout')

@section('title', 'Kiểm kê kho - The Daily Crust')

@section('content')
@php
    $statusStyle = [
        'In_Progress' => ['bg-sky-100 text-sky-800', 'Đang kiểm'],
        'Pending_Approval' => ['bg-amber-100 text-amber-800', 'Chờ Quản lý duyệt'],
        'Completed' => ['bg-emerald-100 text-emerald-800', 'Hoàn tất'],
    ];
@endphp
<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-mint-900">Kiểm kê kho</h1>
            <p class="mt-1 text-sm text-stone-500">Đếm thực tế, đối soát với số hệ thống. Vượt dung sai phải giải trình để Quản lý duyệt.</p>
        </div>
        @if ($canWrite && ! $open)
            <form method="POST" action="{{ route('admin.stocktake.store') }}">
                @csrf
                <button class="rounded-lg bg-mint-700 px-4 py-2 text-sm font-semibold text-white hover:bg-mint-800"><i class="fa-solid fa-play mr-1"></i>Mở phiên kiểm kê mới</button>
            </form>
        @endif
    </div>

    @include('staff.partials.flash')

    @if ($open)
        <div class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
            Đang có phiên chưa chốt: <b>{{ $open->session_name }}</b>.
            <a href="{{ route('admin.stocktake.show', $open) }}" class="font-semibold underline">Tiếp tục kiểm kê</a>
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-[#d8e8d2] text-left text-xs font-bold uppercase tracking-wider text-mint-900">
                <tr><th class="px-4 py-3">Phiên</th><th class="px-4 py-3">Thời gian</th><th class="px-4 py-3">Người tạo</th><th class="px-4 py-3 text-right">Số dòng</th><th class="px-4 py-3">Trạng thái</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($sessions as $s)
                    @php $st = $statusStyle[$s->status] ?? ['bg-stone-100 text-stone-600', $s->status]; @endphp
                    <tr>
                        <td class="px-4 py-2.5 font-medium text-stone-800">{{ $s->session_name }}<div class="text-xs font-normal text-stone-400">{{ $s->note }}</div></td>
                        <td class="px-4 py-2.5 text-stone-600">{{ $s->stocktake_date?->format('H:i d/m/Y') }}</td>
                        <td class="px-4 py-2.5 text-stone-600">{{ $s->creator->full_name ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-right">{{ $s->details_count }}</td>
                        <td class="px-4 py-2.5"><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $st[0] }}">{{ $st[1] }}</span></td>
                        <td class="px-4 py-2.5 text-right"><a href="{{ route('admin.stocktake.show', $s) }}" class="text-sm font-semibold text-mint-700 hover:underline">Xem</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-stone-400">Chưa có phiên kiểm kê.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-stone-100 px-4 py-3">{{ $sessions->links() }}</div>
    </div>
</div>
@endsection
