@extends('layouts.system_layout')

@php $editing = $staff !== null; @endphp
@section('title', ($editing ? 'Sửa' : 'Thêm').' nhân viên - The Daily Crust')
@section('hide_shop_bar', '1')

@section('content')
<div class="mx-auto max-w-2xl space-y-5">
    <div>
        <a href="{{ route('admin.staff.index') }}" class="text-sm text-mint-700 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i>Danh sách nhân viên</a>
        <h1 class="mt-1 text-2xl font-bold text-mint-900">{{ $editing ? 'Sửa tài khoản: '.$staff->full_name : 'Thêm tài khoản nhân viên' }}</h1>
    </div>

    @include('staff.partials.flash')

    <form method="POST" action="{{ $editing ? route('admin.staff.update', $staff) : route('admin.staff.store') }}" class="space-y-4 rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="font-semibold text-stone-700">Tên đăng nhập</span>
                @if ($editing)
                    <input type="text" value="{{ $staff->username }}" disabled class="mt-1 w-full rounded-lg border border-stone-200 bg-stone-100 px-3 py-2 text-stone-500">
                @else
                    <input type="text" name="username" value="{{ old('username') }}" required maxlength="50" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mint-700">
                @endif
            </label>
            <label class="block text-sm">
                <span class="font-semibold text-stone-700">Họ và tên</span>
                <input type="text" name="full_name" value="{{ old('full_name', $staff->full_name ?? '') }}" required maxlength="255" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mint-700">
            </label>
            <label class="block text-sm">
                <span class="font-semibold text-stone-700">Email</span>
                <input type="email" name="email" value="{{ old('email', $staff->email ?? '') }}" required maxlength="255" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mint-700">
            </label>
            <label class="block text-sm">
                <span class="font-semibold text-stone-700">Số điện thoại</span>
                <input type="text" name="phone" inputmode="tel" value="{{ old('phone', $staff?->phoneText()) }}" required maxlength="11" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mint-700">
            </label>
            <label class="block text-sm">
                <span class="font-semibold text-stone-700">Chức vụ</span>
                <input type="text" name="position" list="positions" value="{{ old('position', $staff->position ?? '') }}" required maxlength="100" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mint-700">
                <datalist id="positions">@foreach ($positions as $p)<option value="{{ $p }}">@endforeach</datalist>
            </label>
            <label class="block text-sm">
                <span class="font-semibold text-stone-700">{{ $editing ? 'Mật khẩu mới' : 'Mật khẩu' }}</span>
                <input type="password" name="password" {{ $editing ? '' : 'required' }} minlength="6" autocomplete="new-password"
                       placeholder="{{ $editing ? 'Để trống nếu không đổi' : 'Tối thiểu 6 ký tự' }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mint-700">
            </label>
        </div>

        <p class="text-xs text-stone-500"><i class="fa-solid fa-circle-info mr-1"></i>Tài khoản mới có vai trò <b>Nhân viên (Staff)</b>. Chức vụ quyết định nhóm việc (ví dụ bộ phận bếp nhận thông báo kế hoạch sản xuất).</p>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.staff.index') }}" class="text-sm text-stone-500 hover:underline">Hủy</a>
            <button class="rounded-lg bg-mint-700 px-5 py-2 text-sm font-bold text-white shadow-sm hover:bg-mint-800">{{ $editing ? 'Lưu thay đổi' : 'Tạo tài khoản' }}</button>
        </div>
    </form>
</div>
@endsection
