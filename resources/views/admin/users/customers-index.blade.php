@extends('layouts.system_layout')

@section('title', 'Tài khoản khách hàng - The Daily Crust')

@section('content')
<div class="space-y-5">
    <div>
        <h1 class="text-2xl font-bold text-mint-900"><i class="fa-solid fa-address-book mr-2 text-mint-700"></i>Tài khoản khách hàng</h1>
        <p class="mt-1 text-sm text-stone-500">Quản lý chỉ xem thông tin khách hàng và các đơn online của họ. Không sửa dữ liệu khách ở đây.</p>
    </div>

    <form method="GET" class="flex max-w-md items-center gap-2">
        <input type="text" name="q" value="{{ $q }}" placeholder="Tìm theo tên, tên đăng nhập, email, số điện thoại..." class="w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mint-700">
        <button class="rounded-lg bg-mint-700 px-4 py-2 text-sm font-medium text-white hover:bg-mint-800">Tìm</button>
        @if ($q !== '')<a href="{{ route('admin.customers.index') }}" class="text-sm text-stone-500 hover:underline">Xóa lọc</a>@endif
    </form>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-[#d8e8d2] text-left text-xs font-bold uppercase tracking-wider text-mint-900">
                <tr>
                    <th class="px-4 py-3">Khách hàng</th><th class="px-4 py-3">Tên đăng nhập</th><th class="px-4 py-3">Liên hệ</th>
                    <th class="px-4 py-3 text-right">Số đơn online</th><th class="px-4 py-3 text-right">Tổng giá trị</th><th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
            @forelse ($customers as $c)
                <tr class="hover:bg-stone-50">
                    <td class="px-4 py-3 font-semibold text-stone-900">{{ $c->full_name }}</td>
                    <td class="px-4 py-3 font-mono">{{ $c->username }}</td>
                    <td class="px-4 py-3 text-xs"><div>{{ $c->email }}</div><div>{{ $c->phoneText() }}</div></td>
                    <td class="px-4 py-3 text-right">{{ $c->online_orders_count }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format((int) $c->orders_total, 0, ',', '.') }} đ</td>
                    <td class="px-4 py-3 text-right"><a href="{{ route('admin.customers.show', $c) }}" class="rounded-md border border-stone-300 px-3 py-1.5 text-xs font-semibold text-stone-700 hover:bg-white">Xem</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-stone-500">Không tìm thấy khách hàng nào.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $customers->links() }}
</div>
@endsection
