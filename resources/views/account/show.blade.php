@extends('layouts.system_layout')

@section('title', 'Tài khoản - The Daily Crust')
@section('hide_shop_bar', '1')

@section('content')
@php
    $roleStyle = ['manager' => 'bg-amber-100 text-amber-800', 'staff' => 'bg-sky-100 text-sky-800', 'customer' => 'bg-emerald-100 text-emerald-800'][$user->role] ?? 'bg-stone-100 text-stone-700';
@endphp
<div class="mx-auto max-w-4xl space-y-5">
    <div>
        <h1 class="text-2xl font-bold text-mint-900"><i class="fa-solid fa-user mr-2 text-mint-700"></i>Tài khoản của tôi</h1>
        <p class="mt-1 text-sm text-stone-500">Quản lý thông tin cá nhân và mật khẩu đăng nhập.</p>
    </div>

    @include('staff.partials.flash')

    <div class="grid gap-5 md:grid-cols-3">
        {{-- Thẻ tóm tắt --}}
        <aside class="space-y-4 md:col-span-1">
            <div class="rounded-xl border border-stone-200 bg-white p-5 text-center shadow-sm">
                <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full border border-stone-300 bg-[#eef4ec] text-3xl font-bold uppercase text-mint-800">
                    {{ mb_substr($user->full_name ?: $user->username, 0, 1) }}
                </div>
                <p class="mt-3 text-lg font-bold text-stone-900">{{ $user->full_name ?: $user->username }}</p>
                <span class="mt-1 inline-block rounded-full px-3 py-0.5 text-xs font-semibold {{ $roleStyle }}">{{ $user->roleLabel() }}</span>
                @if ($user->position)<p class="mt-1 text-sm text-stone-500">{{ $user->position }}</p>@endif
                <p class="mt-3 border-t border-stone-100 pt-3 text-xs text-stone-500">Tên đăng nhập: <span class="font-mono font-semibold text-stone-700">{{ $user->username }}</span></p>
            </div>

            <div class="space-y-2 rounded-xl border border-stone-200 bg-white p-4 text-sm shadow-sm">
                @if ($user->isCustomer())
                    <a href="{{ route('orders.mine') }}" class="flex items-center justify-between rounded-lg px-3 py-2 hover:bg-stone-50">
                        <span><i class="fa-solid fa-box-archive mr-2 text-mint-700"></i>Đơn hàng của tôi</span>
                        <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold">{{ $stats['orders'] ?? 0 }}</span>
                    </a>
                @endif
                @if ($user->isManager())
                    <a href="{{ route('admin.staff.index') }}" class="flex items-center justify-between rounded-lg px-3 py-2 hover:bg-stone-50">
                        <span><i class="fa-solid fa-users-gear mr-2 text-mint-700"></i>Tài khoản nhân viên</span>
                        <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold">{{ $stats['staff'] ?? 0 }}</span>
                    </a>
                    <a href="{{ route('admin.customers.index') }}" class="flex items-center justify-between rounded-lg px-3 py-2 hover:bg-stone-50">
                        <span><i class="fa-solid fa-address-book mr-2 text-mint-700"></i>Tài khoản khách hàng</span>
                        <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold">{{ $stats['customers'] ?? 0 }}</span>
                    </a>
                @endif
                <a href="{{ route('notifications.index') }}" class="flex items-center rounded-lg px-3 py-2 hover:bg-stone-50"><i class="fa-regular fa-bell mr-2 text-mint-700"></i>Lịch sử thông báo</a>
            </div>
        </aside>

        <div class="space-y-5 md:col-span-2">
            {{-- Thông tin cá nhân --}}
            <form method="POST" action="{{ route('account.update') }}" class="space-y-4 rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
                @csrf @method('PUT')
                <h2 class="font-bold text-mint-900">Thông tin cá nhân</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm sm:col-span-2">
                        <span class="font-semibold text-stone-700">Họ và tên</span>
                        <input type="text" name="full_name" value="{{ old('full_name', $user->full_name) }}" required maxlength="255"
                               class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mint-700 @error('full_name') border-red-400 @enderror">
                    </label>
                    <label class="block text-sm">
                        <span class="font-semibold text-stone-700">Email</span>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="255"
                               class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mint-700 @error('email') border-red-400 @enderror">
                    </label>
                    <label class="block text-sm">
                        <span class="font-semibold text-stone-700">Số điện thoại</span>
                        <input type="text" name="phone" inputmode="tel" value="{{ old('phone', $user->phoneText()) }}" required maxlength="11"
                               class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mint-700 @error('phone') border-red-400 @enderror">
                    </label>
                    <label class="block text-sm">
                        <span class="font-semibold text-stone-700">Tên đăng nhập</span>
                        <input type="text" value="{{ $user->username }}" disabled class="mt-1 w-full rounded-lg border border-stone-200 bg-stone-100 px-3 py-2 text-stone-500">
                    </label>
                    <label class="block text-sm">
                        <span class="font-semibold text-stone-700">Vai trò{{ $user->position ? ' / Chức vụ' : '' }}</span>
                        <input type="text" value="{{ $user->roleLabel() }}{{ $user->position ? ' · '.$user->position : '' }}" disabled class="mt-1 w-full rounded-lg border border-stone-200 bg-stone-100 px-3 py-2 text-stone-500">
                    </label>
                </div>
                @if ($user->isInternal())
                    <p class="text-xs text-stone-500"><i class="fa-solid fa-circle-info mr-1"></i>Vai trò và chức vụ do Quản lý cấp nên bạn không tự đổi được.</p>
                @endif

                <div class="text-right">
                    <button class="rounded-lg bg-mint-700 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-mint-800">Lưu thay đổi</button>
                </div>
            </form>

            {{-- Đổi mật khẩu --}}
            <form method="POST" action="{{ route('account.password') }}" class="space-y-4 rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
                @csrf @method('PUT')
                <h2 class="font-bold text-mint-900">Đổi mật khẩu</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <label class="block text-sm sm:col-span-3">
                        <span class="font-semibold text-stone-700">Mật khẩu hiện tại</span>
                        <input type="password" name="current_password" required autocomplete="current-password"
                               class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mint-700 sm:max-w-xs @error('current_password') border-red-400 @enderror">
                    </label>
                    <label class="block text-sm sm:col-span-1">
                        <span class="font-semibold text-stone-700">Mật khẩu mới</span>
                        <input type="password" name="password" required minlength="6" autocomplete="new-password"
                               class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mint-700">
                    </label>
                    <label class="block text-sm sm:col-span-1">
                        <span class="font-semibold text-stone-700">Nhập lại mật khẩu mới</span>
                        <input type="password" name="password_confirmation" required minlength="6" autocomplete="new-password"
                               class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mint-700">
                    </label>
                </div>
                <div class="text-right">
                    <button class="rounded-lg border border-mint-700 px-5 py-2 text-sm font-bold text-mint-800 transition hover:bg-[#eef4ec]">Đổi mật khẩu</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
