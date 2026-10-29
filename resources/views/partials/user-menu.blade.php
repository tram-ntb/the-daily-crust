{{-- Menu người dùng góc phải (bấm vào tên/avatar). Dùng chung biến Alpine `profileDropdown` của <body> trong system_layout.
     Customer: "Tài khoản" + "Đơn hàng của tôi".  Manager / Staff: chỉ "Tài khoản" (tài khoản nội bộ không đặt hàng online). --}}
@php
    $u = Auth::user();
    $isCustomer = $u->isCustomer();
    $displayName = $u->full_name ?: $u->username;
@endphp
<div class="relative" @click.outside="profileDropdown = false">
    <button @click="profileDropdown = !profileDropdown" class="flex items-center space-x-3 focus:outline-none" aria-haspopup="true" :aria-expanded="profileDropdown">
        <div class="w-10 h-10 rounded-full bg-[#eef4ec] border border-stone-300 flex items-center justify-center text-mint-800 font-bold uppercase">
            {{ mb_substr($displayName, 0, 1) }}
        </div>
        <div class="text-left hidden sm:block">
            <p class="text-base font-bold text-stone-900 leading-tight max-w-[12rem] truncate">{{ $displayName }}</p>
            <p class="text-xs text-stone-500">{{ $u->position ?: $u->roleLabel() }}</p>
        </div>
        <i class="fa-solid fa-chevron-down text-xs text-stone-400"></i>
    </button>

    <div x-show="profileDropdown" x-cloak x-transition class="absolute right-0 mt-2 w-56 bg-white border border-stone-200 rounded-lg shadow-lg py-1 z-50">
        <div class="px-4 py-2 border-b border-stone-100">
            <p class="text-sm font-bold text-stone-900 truncate">{{ $displayName }}</p>
            <p class="text-xs text-stone-500">{{ $u->roleLabel() }}</p>
        </div>
        <a href="{{ route('account.show') }}" class="block px-4 py-2 text-sm text-stone-700 hover:bg-stone-100"><i class="fa-solid fa-user mr-2"></i> Tài khoản</a>
        @if ($isCustomer)
            <a href="{{ route('orders.mine') }}" class="block px-4 py-2 text-sm text-stone-700 hover:bg-stone-100"><i class="fa-solid fa-box-archive mr-2"></i> Đơn hàng của tôi</a>
        @endif
        <div class="border-t border-stone-100 my-1"></div>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="w-full text-left block px-4 py-2 text-sm text-red-600 hover:bg-stone-100">
                <i class="fa-solid fa-right-from-bracket mr-2"></i> Đăng xuất
            </button>
        </form>
    </div>
</div>
