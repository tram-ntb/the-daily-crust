@extends('layouts.system_layout')

@section('title', 'Worklist đơn hàng - The Daily Crust')

@section('content')
@php
    $statusLabel = [
        'New' => ['Mới', 'bg-sky-100 text-sky-800', 'fa-bell'],
        'Preparing' => ['Đang chuẩn bị', 'bg-amber-100 text-amber-800', 'fa-fire-burner'],
        'Ready' => ['Sẵn sàng', 'bg-emerald-100 text-emerald-800', 'fa-box'],
        'Completed' => ['Hoàn tất', 'bg-stone-100 text-stone-600', 'fa-circle-check'],
    ];
    $nextLabel = ['New' => 'Bắt đầu chuẩn bị', 'Preparing' => 'Đã xong - sẵn sàng', 'Ready' => 'Giao / Khách đã nhận'];
    $tabs = ['active' => 'Cần xử lý', 'New' => 'Mới', 'Preparing' => 'Đang chuẩn bị', 'Ready' => 'Sẵn sàng', 'Completed' => 'Hoàn tất'];
    $methodLabel = ['Cash' => 'Tiền mặt', 'QR_Code' => 'QR', 'Momo' => 'Momo'];
@endphp
<div class="space-y-5"
     x-data="{ open: null, timer: null }"
     x-init="timer = setInterval(() => { if (!document.hidden && open === null && @js($tab !== 'Completed')) location.reload(); }, 30000)">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-mint-900"><i class="fa-solid fa-list-check mr-2 text-mint-700"></i>Worklist đơn hàng</h1>
            <p class="mt-1 text-sm text-stone-500">Đơn khách đặt online hiện ở đây. Bấm vào một đơn để xem món cần chuẩn bị. Trang tự làm mới mỗi 30 giây.</p>
        </div>
        <div class="flex rounded-lg border border-stone-300 bg-white p-0.5 text-sm font-semibold">
            <a href="{{ route('admin.orders.index', ['channel' => 'App']) }}" class="rounded-md px-3 py-1.5 {{ $channel === 'App' ? 'bg-mint-700 text-white' : 'text-stone-600' }}">Online (App)</a>
            <a href="{{ route('admin.orders.index', ['channel' => 'POS']) }}" class="rounded-md px-3 py-1.5 {{ $channel === 'POS' ? 'bg-mint-700 text-white' : 'text-stone-600' }}">Tại quầy (POS)</a>
        </div>
    </div>

    @include('staff.partials.flash')

    <div class="flex flex-wrap gap-2 border-b border-stone-200">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.orders.index', ['channel' => $channel, 'tab' => $key]) }}"
               class="-mb-px inline-flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-semibold {{ $tab === $key ? 'border-mint-700 text-mint-800' : 'border-transparent text-stone-500 hover:text-mint-700' }}">
                {{ $label }}
                <span class="rounded-full px-2 py-0.5 text-xs {{ $key === 'New' && $counts[$key] > 0 ? 'bg-red-500 text-white' : 'bg-stone-100 text-stone-600' }}">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-[#d8e8d2] text-left text-xs font-bold uppercase tracking-wider text-mint-900">
                <tr>
                    <th class="px-4 py-3">Đơn</th><th class="px-4 py-3">Đặt lúc</th><th class="px-4 py-3">Khách / Thu ngân</th>
                    <th class="px-4 py-3">Nhận hàng</th><th class="px-4 py-3 text-right">Tổng tiền</th><th class="px-4 py-3">Trạng thái</th><th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
            @forelse ($orders as $o)
                @php $st = $statusLabel[$o->status] ?? [$o->status, 'bg-stone-100 text-stone-600', 'fa-circle']; @endphp
                <tr class="cursor-pointer hover:bg-stone-50 {{ $o->status === 'New' ? 'bg-sky-50/50' : '' }}" @click="open = open === {{ $o->order_id }} ? null : {{ $o->order_id }}">
                    <td class="px-4 py-3 font-mono font-bold text-mint-800">#{{ $o->order_id }}</td>
                    <td class="px-4 py-3 text-stone-600">
                        {{ $o->order_datetime?->format('H:i d/m') }}
                        @if ($o->status !== 'Completed')<div class="text-xs text-amber-700">{{ $o->order_datetime?->diffForHumans() }}</div>@endif
                    </td>
                    <td class="px-4 py-3">{{ $o->creator->full_name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        @if ($o->delivery_method === 'Delivery_2km')
                            <span class="font-semibold text-sky-700"><i class="fa-solid fa-motorcycle mr-1"></i>Giao tận nơi</span>
                        @else
                            <span class="text-stone-600"><i class="fa-solid fa-store mr-1"></i>Khách tự đến lấy</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right font-bold">{{ number_format($o->total_amount) }}đ <div class="text-xs font-normal text-stone-400">{{ $methodLabel[$o->payment_method] ?? $o->payment_method }}</div></td>
                    <td class="px-4 py-3"><span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $st[1] }}"><i class="fa-solid {{ $st[2] }}"></i>{{ $st[0] }}</span></td>
                    <td class="px-4 py-3 text-right text-stone-400"><i class="fa-solid" :class="open === {{ $o->order_id }} ? 'fa-chevron-up' : 'fa-chevron-down'"></i></td>
                </tr>
                <tr x-show="open === {{ $o->order_id }}" x-cloak class="bg-stone-50">
                    <td colspan="7" class="px-6 py-4">
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="md:col-span-2">
                                <p class="mb-2 text-xs font-bold uppercase tracking-wider text-stone-500">Món cần chuẩn bị</p>
                                <ul class="divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white">
                                    @foreach ($o->lineSummary() as $l)
                                        <li class="flex items-center justify-between px-3 py-2">
                                            <span class="font-medium text-stone-800"><span class="mr-2 inline-block min-w-8 rounded bg-mint-700 px-2 py-0.5 text-center text-xs font-bold text-white">{{ $l['qty'] }}×</span>{{ $l['name'] }}</span>
                                            <span class="text-stone-500">{{ number_format($l['subtotal']) }}đ</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            <div class="space-y-2 text-sm">
                                @if ($o->note)<p class="rounded-lg bg-amber-50 px-3 py-2 text-amber-800"><i class="fa-solid fa-note-sticky mr-1"></i>{{ $o->note }}</p>@endif
                                @if ($o->delivery_address)<p class="text-stone-600"><i class="fa-solid fa-location-dot mr-1 text-sky-600"></i>{{ $o->delivery_address }}</p>@endif
                                <p class="text-stone-500">Thanh toán: {{ $methodLabel[$o->payment_method] ?? $o->payment_method }} · {{ $o->payment?->status === 'Completed' ? 'đã thu' : 'chưa thu' }}</p>
                                @if ($canUpdate && isset($nextLabel[$o->status]))
                                    <form method="POST" action="{{ route('admin.orders.advance', $o) }}" @click.stop>
                                        @csrf
                                        <button class="w-full rounded-lg bg-mint-700 px-4 py-2.5 font-semibold text-white hover:bg-mint-800"><i class="fa-solid fa-forward-step mr-1"></i>{{ $nextLabel[$o->status] }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-12 text-center text-stone-400"><i class="fa-regular fa-face-smile mb-2 text-3xl"></i><div>Không có đơn nào trong mục này.</div></td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="border-t border-stone-100 px-4 py-3">{{ $orders->links() }}</div>
    </div>
</div>
@endsection

@push('styles')
<style>[x-cloak]{display:none !important}</style>
@endpush
