@extends('layouts.system_layout')

@section('title', 'Quản lý tài khoản nhân viên - The Daily Crust')

@section('content')
<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-mint-900"><i class="fa-solid fa-users-gear mr-2 text-mint-700"></i>Quản lý tài khoản nhân viên</h1>
            <p class="mt-1 text-sm text-stone-500">Quản lý tạo, sửa và khóa tài khoản nhân viên. Tài khoản Quản lý chỉ xem, không sửa ở đây.</p>
        </div>
        <a href="{{ route('admin.staff.create') }}" class="rounded-lg bg-mint-700 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-mint-800"><i class="fa-solid fa-user-plus mr-1"></i>Thêm nhân viên</a>
    </div>

    @include('staff.partials.flash')

    <form method="GET" class="flex max-w-md items-center gap-2">
        <input type="text" name="q" value="{{ $q }}" placeholder="Tìm theo tên, tên đăng nhập, email, chức vụ..." class="w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mint-700">
        <button class="rounded-lg bg-mint-700 px-4 py-2 text-sm font-medium text-white hover:bg-mint-800">Tìm</button>
        @if ($q !== '')<a href="{{ route('admin.staff.index') }}" class="text-sm text-stone-500 hover:underline">Xóa lọc</a>@endif
    </form>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-[#d8e8d2] text-left text-xs font-bold uppercase tracking-wider text-mint-900">
                <tr>
                    <th class="px-4 py-3">Họ tên</th><th class="px-4 py-3">Tên đăng nhập</th><th class="px-4 py-3">Vai trò</th>
                    <th class="px-4 py-3">Chức vụ</th><th class="px-4 py-3">Liên hệ</th><th class="px-4 py-3">Trạng thái</th><th class="px-4 py-3 text-right">Thao tác</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
            @forelse ($users as $u)
                <tr class="{{ $u->isLocked() ? 'bg-stone-50 text-stone-400' : '' }}">
                    <td class="px-4 py-3 font-semibold text-stone-900">{{ $u->full_name }}@if ($u->user_id === auth()->id()) <span class="ml-1 text-xs font-normal text-mint-700">(bạn)</span>@endif</td>
                    <td class="px-4 py-3 font-mono">{{ $u->username }}</td>
                    <td class="px-4 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $u->isManager() ? 'bg-amber-100 text-amber-800' : 'bg-sky-100 text-sky-800' }}">{{ $u->roleLabel() }}</span></td>
                    <td class="px-4 py-3">{{ $u->position }}</td>
                    <td class="px-4 py-3 text-xs"><div>{{ $u->email }}</div><div>{{ $u->phoneText() }}</div></td>
                    <td class="px-4 py-3">
                        @if ($u->isLocked())<span class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">Đã khóa</span>
                        @else<span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Hoạt động</span>@endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        @if ($u->isStaff())
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.staff.edit', $u) }}" class="rounded-md border border-stone-300 px-3 py-1.5 text-xs font-semibold text-stone-700 hover:bg-stone-50"><i class="fa-solid fa-pen mr-1"></i>Sửa</a>
                                <form method="POST" action="{{ route('admin.staff.toggle', $u) }}"
                                      onsubmit="return confirm('{{ $u->isLocked() ? 'Mở khóa' : 'Khóa' }} tài khoản {{ e($u->full_name) }}?')">
                                    @csrf
                                    <button class="rounded-md px-3 py-1.5 text-xs font-semibold {{ $u->isLocked() ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-red-50 text-red-700 hover:bg-red-100' }}">
                                        <i class="fa-solid {{ $u->isLocked() ? 'fa-lock-open' : 'fa-lock' }} mr-1"></i>{{ $u->isLocked() ? 'Mở khóa' : 'Khóa' }}
                                    </button>
                                </form>
                            </div>
                        @else
                            <span class="text-xs text-stone-400">Chỉ xem</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-stone-500">Không tìm thấy tài khoản nào.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
</div>
@endsection
