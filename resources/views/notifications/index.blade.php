@extends('layouts.system_layout')

@section('title', 'Thông báo - The Daily Crust')
@section('hide_shop_bar', '1')

@section('content')
@php
    $tone = [
        'emerald' => 'bg-emerald-100 text-emerald-700', 'sky' => 'bg-sky-100 text-sky-700', 'amber' => 'bg-amber-100 text-amber-700',
        'red' => 'bg-red-100 text-red-700', 'stone' => 'bg-stone-100 text-stone-600',
    ];
@endphp
<div class="mx-auto max-w-3xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-mint-900"><i class="fa-regular fa-bell mr-2 text-mint-700"></i>Thông báo</h1>
            <p class="mt-1 text-sm text-stone-500">Lịch sử thông báo của riêng bạn{{ $unread > 0 ? " · {$unread} chưa đọc" : '' }}.</p>
        </div>
        @if ($unread > 0)
            <form method="POST" action="{{ route('notifications.readAll') }}">
                @csrf
                <button class="rounded-lg border border-mint-700 px-4 py-2 text-sm font-semibold text-mint-800 hover:bg-[#eef4ec]"><i class="fa-solid fa-check-double mr-1"></i>Đánh dấu tất cả đã đọc</button>
            </form>
        @endif
    </div>

    @include('staff.partials.flash')

    <div class="flex gap-2 border-b border-stone-200 text-sm font-semibold">
        <a href="{{ route('notifications.index') }}" class="-mb-px border-b-2 px-4 py-2.5 {{ $filter === 'all' ? 'border-mint-700 text-mint-800' : 'border-transparent text-stone-500 hover:text-mint-700' }}">Tất cả</a>
        <a href="{{ route('notifications.index', ['filter' => 'unread']) }}" class="-mb-px border-b-2 px-4 py-2.5 {{ $filter === 'unread' ? 'border-mint-700 text-mint-800' : 'border-transparent text-stone-500 hover:text-mint-700' }}">
            Chưa đọc <span class="ml-1 rounded-full {{ $unread > 0 ? 'bg-red-500 text-white' : 'bg-stone-100 text-stone-600' }} px-2 py-0.5 text-xs">{{ $unread }}</span>
        </a>
    </div>

    <div class="divide-y divide-stone-100 overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
        @forelse ($items as $n)
            <div class="flex items-start gap-3 px-4 py-3.5 {{ $n['read'] ? '' : 'bg-[#eef4ec]/60' }}">
                <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $tone[$n['color']] ?? $tone['stone'] }}">
                    <i class="fa-solid {{ $n['icon'] }}"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-stone-900 {{ $n['read'] ? 'font-medium' : 'font-bold' }}">{{ $n['title'] }}</p>
                    <p class="mt-0.5 text-sm text-stone-600">{{ $n['body'] }}</p>
                    <p class="mt-1 text-xs text-stone-400">{{ $n['at'] }} · {{ $n['time'] }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-2 text-xs font-semibold">
                    <form method="POST" action="{{ route('notifications.read', ['id' => $n['id'], 'go' => 1]) }}">
                        @csrf
                        <button class="rounded-md bg-mint-700 px-3 py-1.5 text-white hover:bg-mint-800">Xem</button>
                    </form>
                    @unless ($n['read'])
                        <form method="POST" action="{{ route('notifications.read', ['id' => $n['id']]) }}">
                            @csrf
                            <button class="rounded-md border border-stone-300 px-3 py-1.5 text-stone-600 hover:bg-stone-50" title="Đánh dấu đã đọc">Đã đọc</button>
                        </form>
                    @endunless
                </div>
            </div>
        @empty
            <div class="p-10 text-center text-stone-500">
                <i class="fa-regular fa-bell-slash mb-2 text-3xl text-mint-700/60"></i>
                <p>{{ $filter === 'unread' ? 'Bạn đã đọc hết thông báo.' : 'Chưa có thông báo nào.' }}</p>
            </div>
        @endforelse
    </div>

    {{ $items->links() }}
</div>
@endsection
