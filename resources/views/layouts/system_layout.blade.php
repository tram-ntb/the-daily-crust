<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'The Daily Crust - Artisan Bakery')</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        mint: {
                            600: '#3b7a57',
                            700: '#2e6144',
                            800: '#224833',
                            900: '#173322',
                            light: '#eef4ec',
                            active: '#2e6144',
                        }
                    }
                }
            }
        }
    </script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style> body { font-family: 'Times New Roman', Times, serif; } [x-cloak] { display: none !important; } </style>
    @stack('styles')
</head>
<body class="bg-stone-50 antialiased min-h-screen flex flex-col" x-data="{ sidebarOpen: true, profileDropdown: false, filterDropdown: false, groupDropdown: false, favDropdown: false }">

    <div class="flex flex-1 min-h-screen">
        
        <!-- SIDEBAR BÊN TRÁI: TỰ ĐỘNG THAY ĐỔI THEO ROLE -->
        <aside :class="sidebarOpen ? 'w-64' : 'w-20'" class="bg-[#eef4ec] border-r border-stone-200 transition-all duration-300 flex flex-col z-20 shadow-sm shrink-0 sticky top-0 h-screen">
            
            <div class="p-4 border-b border-stone-300/50 flex items-center justify-between min-h-[70px]">
                <span x-show="sidebarOpen" class="font-extrabold text-mint-800 text-base tracking-wider">
                    @auth
                        @if(Auth::user()->role == 'manager') QUẢN TRỊ HỆ THỐNG
                        @elseif(Auth::user()->role == 'staff') KHU VỰC NHÂN VIÊN
                        @else QUẢN LÝ DANH MỤC
                        @endif
                    @else
                        QUẢN LÝ DANH MỤC
                    @endauth
                </span>
                <button @click="sidebarOpen = !sidebarOpen" class="text-mint-800 hover:text-mint-600 focus:outline-none p-2 rounded-lg bg-white shadow-xs">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
            </div>

            <nav class="flex-1 py-4 px-3 space-y-1 text-base font-medium overflow-y-auto">
                
                @php
                    $role = Auth::user()->role ?? 'customer';
                @endphp

                <!-- 1. MENU DÀNH CHO KHÁCH HÀNG / VÃNG LAI HOẶC TRANG CHUNG -->
                @if($role == 'customer')
                    @php
                        $currentCategory = request('category');
                        $isHome = !request()->has('category') || request('category') == '';
                    @endphp

                    <a href="{{ route('home') }}" class="flex items-center px-3 py-2.5 rounded-lg {{ $isHome ? 'bg-mint-700 text-white shadow-xs' : 'text-stone-800 hover:bg-[#d8e8d2]' }} transition">
                        <i class="fa-solid fa-house w-6 text-center {{ $isHome ? 'text-white' : 'text-mint-700' }}"></i>
                        <span x-show="sidebarOpen" class="ml-3">Trang chủ</span>
                    </a>

                    @php
                        $categories = DB::table('products')->select('category')->distinct()->pluck('category');
                    @endphp

                    @foreach($categories as $cat)
                    @php
                        $isActive = ($currentCategory == $cat);
                        $iconClass = 'fa-cookie-bite';
                        if(stripos($cat, 'bánh mì') !== false) $iconClass = 'fa-bread-slice';
                        elseif(stripos($cat, 'bánh ngọt') !== false || stripos($cat, 'cookies') !== false) $iconClass = 'fa-cake-candles';
                        elseif(stripos($cat, 'đồ uống') !== false) $iconClass = 'fa-mug-hot';
                        elseif(stripos($cat, 'mặn') !== false) $iconClass = 'fa-burger';
                        elseif(stripos($cat, 'thương mại') !== false || stripos($cat, 'quà') !== false) $iconClass = 'fa-gift';
                    @endphp
                    <a href="{{ url('/?category=' . urlencode($cat)) }}" class="flex items-center px-3 py-2.5 rounded-lg {{ $isActive ? 'bg-mint-700 text-white shadow-xs' : 'text-stone-800 hover:bg-[#d8e8d2]' }} transition">
                        <i class="fa-solid {{ $iconClass }} w-6 text-center {{ $isActive ? 'text-white' : 'text-mint-700' }}"></i>
                        <span x-show="sidebarOpen" class="ml-3 truncate">{{ $cat }}</span>
                    </a>
                    @endforeach
                @endif

                <!-- 2 + 3. MENU STAFF / MANAGER (cùng khung, khác nhóm chức năng) -->
                @if($role !== 'customer')
                    @php
                        $adminUrl = fn (string $name) => \Illuminate\Support\Facades\Route::has($name) ? route($name) : '#';
                        $navLink = function (string $routeName, string $icon, string $label) use ($adminUrl) {
                            $active = request()->routeIs($routeName);
                            $cls = $active ? 'bg-mint-700 text-white shadow-xs' : 'text-stone-800 hover:bg-[#d8e8d2]';
                            $icn = $active ? 'text-white' : 'text-mint-700';
                            return '<a href="' . e($adminUrl($routeName)) . '" class="flex items-center px-3 py-2.5 rounded-lg ' . $cls . ' transition">'
                                . '<i class="fa-solid ' . $icon . ' w-6 text-center ' . $icn . '"></i>'
                                . '<span x-show="sidebarOpen" class="ml-3 truncate">' . e($label) . '</span></a>';
                        };
                        $navHeading = fn (string $t) => '<div x-show="sidebarOpen" class="px-3 pt-4 pb-1 text-xs font-bold text-mint-900 uppercase tracking-wider">' . e($t) . '</div>';
                    @endphp

                    @if($role === 'staff')
                        {!! $navLink('admin.dashboard', 'fa-chart-pie', 'Dashboard') !!}
                        {!! $navHeading('Bán hàng') !!}
                        {!! $navLink('admin.pos', 'fa-cash-register', 'Bán hàng POS') !!}
                        {!! $navLink('admin.orders.index', 'fa-file-invoice-dollar', 'Quản lý Đơn hàng') !!}
                        {!! $navHeading('Kho & Sản xuất') !!}
                        {!! $navLink('admin.products.stock', 'fa-boxes-stacked', 'Sản phẩm & Tồn kho') !!}
                        {!! $navLink('admin.ingredients.index', 'fa-cubes', 'Quản lý Nguyên liệu') !!}
                        {!! $navLink('admin.stocktake.index', 'fa-clipboard-check', 'Kiểm kê kho (Stocktake)') !!}
                        {!! $navLink('admin.waste.index', 'fa-triangle-exclamation', 'Tiêu hủy hàng hỏng') !!}
                        {!! $navLink('admin.production.index', 'fa-industry', 'Sản xuất hôm nay') !!}
                        {!! $navLink('admin.batches.index', 'fa-fire-burner', 'Theo dõi mẻ nướng') !!}
                        {!! $navLink('admin.bom.index', 'fa-book-open', 'Công thức & Định lượng BOM') !!}
                        {!! $navLink('admin.expiry.index', 'fa-hourglass-half', 'Hàng cận date & Phiếu hủy') !!}
                    @else
                        {!! $navLink('admin.dashboard', 'fa-chart-pie', 'Dashboard') !!}
                        {!! $navHeading('Dự báo & Sản xuất') !!}
                        {!! $navLink('admin.forecast.index', 'fa-chart-line', 'Dự báo nhu cầu (ML)') !!}
                        {!! $navLink('admin.plans.index', 'fa-calendar-check', 'Lập kế hoạch sản xuất') !!}
                        {!! $navLink('admin.production.index', 'fa-industry', 'Tiến độ sản xuất') !!}
                        {!! $navLink('admin.batches.index', 'fa-fire-burner', 'Theo dõi mẻ nướng') !!}
                        {!! $navLink('admin.bom.index', 'fa-book-open', 'Công thức & Định lượng BOM') !!}
                        {!! $navHeading('Kho & Mua hàng') !!}
                        {!! $navLink('admin.ingredients.index', 'fa-boxes-stacked', 'Kho & Xử lý thiếu hụt NL') !!}
                        {!! $navLink('admin.stocktake.index', 'fa-clipboard-check', 'Kiểm kê & Duyệt giải trình') !!}
                        {!! $navLink('admin.waste.index', 'fa-triangle-exclamation', 'Duyệt phiếu hủy') !!}
                        {!! $navLink('admin.expiry.index', 'fa-hourglass-half', 'Hàng cận date') !!}
                        {!! $navLink('admin.purchase.index', 'fa-cart-flatbed', 'Phiếu mua & Đơn mua NCC') !!}
                        {!! $navHeading('Kinh doanh') !!}
                        {!! $navLink('admin.reports.revenue', 'fa-file-waveform', 'Báo cáo & Thống kê doanh thu') !!}
                        {!! $navHeading('Hệ thống') !!}
                        {!! $navLink('admin.staff.index', 'fa-users-gear', 'Quản lý tài khoản nhân viên') !!}
                        {!! $navLink('admin.customers.index', 'fa-address-book', 'Tài khoản khách hàng') !!}
                    @endif
                @endif

            </nav>
            @auth
                @if($role !== 'customer')
                    <div class="p-3 border-t border-stone-300/50 flex items-center gap-3">
                        <div class="w-10 h-10 shrink-0 rounded-full bg-white border border-stone-300 flex items-center justify-center text-mint-800 font-bold uppercase">
                            {{ mb_substr(Auth::user()->full_name ?? Auth::user()->username, 0, 1) }}
                        </div>
                        <div x-show="sidebarOpen" class="min-w-0">
                            <p class="text-sm font-bold text-stone-900 truncate">{{ Auth::user()->full_name ?? Auth::user()->username }}</p>
                            <p class="text-xs text-stone-500 truncate">{{ Auth::user()->position }}</p>
                        </div>
                    </div>
                @endif
            @endauth
        </aside>

        <!-- KHUNG CHÍNH BÊN PHẢI -->
        <div class="flex-1 flex flex-col min-w-0">
            
            <header class="h-20 bg-white border-b border-stone-200 flex items-center justify-between px-6 z-30 shadow-xs sticky top-0">
                <div class="flex items-center space-x-3">
                    <img src="{{ asset('images/logo.png') }}" alt="Logo" class="w-16 h-16 object-contain rounded-full bg-white p-1 shadow">
                    <span class="font-extrabold text-mint-800 text-xl tracking-tight">The Daily Crust</span>
                </div>

                <div class="flex items-center space-x-5 ml-auto">
                    @guest
                        <div class="flex items-center space-x-3">
                            <a href="{{ route('login') }}" class="text-stone-700 hover:text-amber-800 font-semibold text-sm px-3 py-2 transition">Đăng nhập</a>
                            <span class="text-stone-300">/</span>
                            <a href="{{ route('login') }}" class="bg-amber-700 hover:bg-amber-800 text-white font-bold text-sm px-4 py-2 rounded-lg shadow-xs transition">Đăng ký</a>
                        </div>
                    @else
                        @include('partials.notification-bell')
                        @include('partials.user-menu')
                    @endguest
                </div>
            </header>

            <!-- NỘI DUNG CHÍNH (Chỉ hiển thị công cụ mua sắm khi là khách hàng) -->
            <main class="flex-1 p-6 bg-stone-50">
                @php $hideShopBar = trim($__env->yieldContent('hide_shop_bar')) !== ''; @endphp
                @if($role == 'customer' && ! $hideShopBar)
                    <form action="{{ url('/') }}" method="GET" class="flex flex-col lg:flex-row items-center justify-between mb-6 gap-4">
                        <div class="relative w-full lg:w-80 flex items-center space-x-2">
                            <div class="relative flex-1">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-stone-400"><i class="fa-solid fa-magnifying-glass"></i></span>
                                <input type="text" name="search" value="{{ request('search') }}" placeholder="Nhập từ khóa tìm kiếm ..." class="w-full pl-10 pr-4 py-2 bg-white border border-stone-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-mint-700 shadow-xs">
                            </div>
                            <button type="submit" class="px-4 py-2 bg-mint-700 text-white rounded-lg text-base font-medium hover:bg-mint-800 shadow-xs transition">Tìm</button>
                        </div>

                        <div class="flex flex-wrap items-center space-x-3 w-full lg:w-auto justify-end gap-y-2 relative">
                            <!-- Bộ lọc & Yêu thích giữ nguyên như cũ -->
                            <div class="relative" @click.outside="filterDropdown = false">
                                <button type="button" @click="filterDropdown = !filterDropdown" class="px-4 py-2 bg-white border border-stone-300 rounded-lg text-base font-medium text-stone-700 hover:bg-stone-100 shadow-xs flex items-center space-x-2">
                                    <i class="fa-solid fa-filter text-stone-500"></i><span>Lọc</span>
                                </button>
                                <div x-show="filterDropdown" class="absolute right-0 mt-2 w-72 bg-white border border-stone-200 rounded-xl shadow-xl p-4 z-30 text-sm">
                                    <div class="font-bold text-stone-800 mb-1 flex items-center justify-between"><span>Khoảng giá</span><span class="text-[11px] text-stone-400 font-normal">(Đơn vị: nghìn đồng)</span></div>
                                    <div class="flex items-center space-x-2 mb-3">
                                        <input type="number" name="price_from" value="{{ request('price_from') }}" placeholder="Từ (vd: 17)" class="w-1/2 px-2.5 py-1.5 border border-stone-300 rounded-lg text-sm">
                                        <input type="number" name="price_to" value="{{ request('price_to') }}" placeholder="Đến (vd: 35)" class="w-1/2 px-2.5 py-1.5 border border-stone-300 rounded-lg text-sm">
                                    </div>
                                    <button type="submit" class="w-full py-2 bg-mint-700 text-white rounded-lg text-xs font-bold shadow-xs hover:bg-mint-800 transition mb-3">Áp dụng lọc giá</button>
                                </div>
                            </div>
                            <!-- 2. Nút Nhóm sản phẩm (Group By / Sort) -->
                <div class="relative" @click.outside="groupDropdown = false">
                    <button type="button" @click="groupDropdown = !groupDropdown" class="px-4 py-2 bg-white border border-stone-300 rounded-lg text-base font-medium text-stone-700 hover:bg-stone-100 shadow-xs flex items-center space-x-2">
                        <i class="fa-solid fa-bars-staggered text-stone-500"></i>
                        <span>Nhóm sản phẩm</span>
                    </button>
                    <div x-show="groupDropdown" class="absolute right-0 mt-2 w-56 bg-white border border-stone-200 rounded-lg shadow-lg py-2 z-30 text-sm">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'best_seller']) }}" class="block px-4 py-2 hover:bg-stone-100"><i class="fa-solid fa-fire text-amber-600 mr-2"></i> Sản phẩm bán chạy</a>
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'price_asc']) }}" class="block px-4 py-2 hover:bg-stone-100"><i class="fa-solid fa-arrow-up-wide-short mr-2"></i> Giá: Thấp đến Cao</a>
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'price_desc']) }}" class="block px-4 py-2 hover:bg-stone-100"><i class="fa-solid fa-arrow-down-wide-short mr-2"></i> Giá: Cao đến Thấp</a>
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'seasonal']) }}" class="block px-4 py-2 hover:bg-stone-100"><i class="fa-solid fa-seedling text-emerald-600 mr-2"></i> Sản phẩm theo mùa & Dự báo</a>
                    </div>
                </div>

                <!-- 3. Nút Yêu thích (Favorites) -->
                <div class="relative" @click.outside="favDropdown = false">
                    <button type="button" @click="favDropdown = !favDropdown" class="px-4 py-2 bg-mint-700 text-white rounded-lg text-base font-medium hover:bg-mint-800 shadow-xs flex items-center space-x-2">
                        <i class="fa-solid fa-heart"></i>
                        <span>Yêu thích</span>
                    </button>
                    <div x-show="favDropdown" class="absolute right-0 mt-2 w-56 bg-white border border-stone-200 rounded-lg shadow-lg py-2 z-30 text-sm">
                        @auth
                            <a href="#" class="block px-4 py-2 hover:bg-stone-100"><i class="fa-solid fa-heart text-red-500 mr-2"></i> Danh sách yêu thích cá nhân</a>
                            <a href="#" class="block px-4 py-2 hover:bg-stone-100"><i class="fa-solid fa-rotate-right text-mint-700 mr-2"></i> Sản phẩm mua thường xuyên</a>
                            <a href="#" class="block px-4 py-2 hover:bg-stone-100"><i class="fa-solid fa-utensils text-amber-700 mr-2"></i> Món ăn kèm gợi ý</a>
                        @else
                            <a href="javascript:void(0);" onclick="alert('Vui lòng đăng nhập để xem danh sách yêu thích cá nhân!');" class="block px-4 py-2 hover:bg-stone-100"><i class="fa-solid fa-heart text-red-500 mr-2"></i> Danh sách yêu thích cá nhân</a>
                            <a href="javascript:void(0);" onclick="alert('Vui lòng đăng nhập để xem sản phẩm mua thường xuyên!');" class="block px-4 py-2 hover:bg-stone-100"><i class="fa-solid fa-rotate-right text-mint-700 mr-2"></i> Sản phẩm mua thường xuyên</a>
                            <a href="javascript:void(0);" onclick="alert('Vui lòng đăng nhập để xem món ăn kèm gợi ý!');" class="block px-4 py-2 hover:bg-stone-100"><i class="fa-solid fa-utensils text-amber-700 mr-2"></i> Món ăn kèm gợi ý</a>
                        @endauth
                    </div>
                </div>
                        </div>
                    </form>
                @endif

                @yield('content')
            </main>

            @if (request()->routeIs('home', 'dashboard', 'admin.dashboard'))
                <footer class="bg-gradient-to-r from-mint-900 to-mint-800 text-stone-300 pt-10 pb-6 px-8 text-sm shadow-inner">
                    <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-8 mb-8 items-center">
                        <div class="flex items-center space-x-4">
                            <img src="{{ asset('images/logo.png') }}" alt="Logo" class="w-14 h-14 rounded-full bg-white p-1 object-contain shadow">
                            <div>
                                <span class="font-bold text-white text-lg tracking-wide block">The Daily Crust</span>
                                <span class="text-xs text-stone-400">Hệ thống quản lý chuỗi tiệm bánh tươi & Dự báo nhu cầu</span>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-2 md:items-end font-medium">
                            <a href="#" class="hover:text-white transition">Liên hệ</a>
                            <a href="#" class="hover:text-white transition">Về chúng tôi</a>
                            <a href="#" class="hover:text-white transition">Chính sách bảo mật</a>
                        </div>
                    </div>
                    <div class="max-w-7xl mx-auto border-t border-mint-700/60 pt-4 text-center text-xs text-stone-400">
                        Copyright © 2026 The Daily Crust. All rights reserved.
                    </div>
                </footer>
            @endif
        </div>
    </div>

    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    @stack('scripts')
</body>
</html>
