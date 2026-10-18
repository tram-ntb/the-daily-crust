<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;0,900;1,600&display=swap" rel="stylesheet">

        <title>Đăng nhập / Đăng ký | The Daily Crust</title>

        <script src="https://cdn.tailwindcss.com"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
        <style>
            body { font-family: 'Times New Roman', Times, serif; }
            .font-serif-title { font-family: 'Playfair Display', serif; }
        </style>
    </head>
    <body class="min-h-screen bg-[radial-gradient(circle_at_top_left,_#eef4ec_0%,_#d8e8d2_100%)] text-slate-900 antialiased" x-data="{ isLogin: true }">
        <main class="min-h-screen p-3 sm:p-6 lg:p-8 flex items-center justify-center">
            <!-- Đã đổi từ xl:grid-cols thành lg:grid-cols để hiển thị ổn định ngay cả khi zoom 110% hoặc trên màn hình laptop nhỏ -->
            <div class="mx-auto grid min-h-[calc(100vh-2rem)] w-full max-w-7xl overflow-hidden rounded-[2rem] border border-white/70 bg-white/90 shadow-[0_30px_100px_-35px_rgba(34,72,51,0.35)] backdrop-blur lg:grid-cols-[1.2fr_0.9fr]">
                
                <!-- CỘT BÊN TRÁI: GIỚI THIỆU HỆ THỐNG VÀ HÌNH MINH HỌA -->
                <section class="relative hidden flex-col justify-between overflow-hidden bg-[linear-gradient(180deg,#eef4ec_0%,#c8dcc3_100%)] p-8 lg:p-10 lg:flex">
                    <div class="absolute inset-0 opacity-80">
                        <div class="absolute left-[-2rem] top-6 h-44 w-44 rounded-full bg-white/40 blur-2xl"></div>
                        <div class="absolute right-12 top-16 h-52 w-52 rounded-full bg-emerald-300/30 blur-3xl"></div>
                    </div>

                    <div class="relative z-10 max-w-2xl space-y-5">
                        <div class="flex items-center space-x-4">
                            <img src="{{ asset('images/logo.png') }}" alt="Logo" class="w-14 h-14 rounded-full bg-white p-1 shadow-md object-contain">
                            <h2 class="text-2xl lg:text-3xl font-black tracking-tight text-[#173322] font-serif-title">
                                THE DAILY CRUST - ARTISAN BAKERY
                            </h2>
                        </div>
                        <p class="text-sm lg:text-base leading-relaxed text-slate-700">
                            Hệ thống quản lý chuỗi tiệm bánh tươi tích hợp mô hình dự báo nhu cầu sản xuất thông minh, tối ưu hóa nguyên liệu và hạn chế tối đa lãng phí dựa trên phân tích dữ liệu lịch sử.
                        </p>

                        <!-- 3 Hộp thông tin -->
                        <div class="grid max-w-2xl grid-cols-3 gap-3 pt-1">
                            <div class="rounded-2xl border border-white/80 bg-white/80 p-3 shadow-sm backdrop-blur text-center flex flex-col items-center justify-center">
                                <p class="text-sm font-bold text-[#224833]">Khách hàng</p>
                                <p class="mt-1 text-[11px] text-slate-600">Đặt bánh, lưu món yêu thích & theo dõi đơn</p>
                            </div>
                            <div class="rounded-2xl border border-white/80 bg-white/80 p-3 shadow-sm backdrop-blur text-center flex flex-col items-center justify-center">
                                <p class="text-sm font-bold text-[#224833]">Nhân viên</p>
                                <p class="mt-1 text-[11px] text-slate-600">Kiểm kê kho, vận hành mẻ nướng & sản xuất BOM</p>
                            </div>
                            <div class="rounded-2xl border border-white/80 bg-white/80 p-3 shadow-sm backdrop-blur text-center flex flex-col items-center justify-center">
                                <p class="text-sm font-bold text-[#224833]">Quản lý</p>
                                <p class="mt-1 text-[11px] text-slate-600">Kiểm soát mô hình dự báo nhu cầu & báo cáo dữ liệu</p>
                            </div>
                        </div>
                    </div>

                    <!-- Hình minh họa trực quan -->
                    <div class="relative z-10 flex items-center justify-center mt-2">
                        <div class="relative w-full max-w-sm">
                            <svg viewBox="0 0 650 300" xmlns="http://www.w3.org/2000/svg" class="w-full h-auto">
                                <g transform="translate(50, 10)">
                                    <ellipse cx="275" cy="230" rx="180" ry="15" fill="#2e6144" opacity="0.15"/>
                                    
                                    <g transform="translate(40, 30)">
                                        <rect x="0" y="0" width="150" height="100" rx="8" fill="#173322" opacity="0.9"/>
                                        <rect x="12" y="12" width="126" height="55" rx="4" fill="#eef4ec"/>
                                        <polyline points="20,50 45,35 70,42 95,25 120,18" fill="none" stroke="#2e6144" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                        <circle cx="120" cy="18" r="4" fill="#2e6144"/>
                                        <rect x="65" y="100" width="20" height="35" fill="#2e6144"/>
                                        <rect x="45" y="132" width="60" height="5" rx="2" fill="#224833"/>
                                    </g>

                                    <g transform="translate(220, 50)">
                                        <rect x="0" y="0" width="120" height="80" rx="10" fill="#2e6144" opacity="0.9"/>
                                        <rect x="12" y="12" width="96" height="45" rx="5" fill="#eef4ec"/>
                                        <ellipse cx="60" cy="35" rx="25" ry="6" fill="#c8dcc3"/>
                                        <path d="M42,35 Q60,20 78,35" fill="none" stroke="#173322" stroke-width="2"/>
                                        <circle cx="25" cy="65" r="4" fill="#c8dcc3"/>
                                        <circle cx="40" cy="65" r="4" fill="#c8dcc3"/>
                                    </g>

                                    <g opacity="0.8">
                                        <g transform="translate(380, 30)">
                                            <path d="M10,20 Q25,5 40,20 Q45,30 30,35 Q15,35 10,20 Z" fill="#224833"/>
                                        </g>
                                        <g transform="translate(360, 95)">
                                            <rect x="0" y="0" width="30" height="25" rx="4" fill="#3b7a57" opacity="0.8"/>
                                            <line x1="6" y1="8" x2="24" y2="8" stroke="white" stroke-width="2"/>
                                            <line x1="6" y1="15" x2="16" y2="15" stroke="white" stroke-width="2"/>
                                        </g>
                                    </g>
                                </g>
                            </svg>
                        </div>
                    </div>
                </section>

                <!-- CỘT BÊN PHẢI: FORM ĐĂNG NHẬP / ĐĂNG KÝ -->
                <section class="flex w-full items-center justify-center bg-white px-5 py-8 sm:px-8 lg:px-10">
                    <div class="w-full max-w-[26rem] rounded-[1.75rem] border border-slate-100 bg-white p-6 shadow-[0_18px_50px_-24px_rgba(34,72,51,0.35)] sm:p-8">
                        
                        <div class="mb-6 text-left">                            
                            <h2 class="text-[1.85rem] font-black tracking-tight text-slate-950" x-text="isLogin ? 'Đăng nhập hệ thống' : 'Đăng ký tài khoản'"></h2>
                            <p class="mt-2 text-sm leading-6 text-slate-600" x-text="isLogin ? 'Vui lòng nhập thông tin tài khoản để tiếp tục.' : 'Tạo tài khoản mới để trải nghiệm dịch vụ.'"></p>
                        </div>

                        @if ($errors->any())
                            <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        @if (session('success'))
                            <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                                {{ session('success') }}
                            </div>
                        @endif

                        <!-- FORM ĐĂNG NHẬP -->
                        <form method="POST" action="{{ route('login.store') }}" class="space-y-4" x-show="isLogin">
                            @csrf
                            <div>
                                <label for="login" class="mb-2 block text-sm font-medium text-slate-700">Tên đăng nhập</label>
                                <input id="login" name="login" value="{{ old('login') }}" type="text" required placeholder="Nhập tên đăng nhập" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm outline-none transition focus:border-[#2e6144] focus:ring-4 focus:ring-[#2e6144]/10">
                            </div>

                            <div>
                                <label for="password" class="mb-2 block text-sm font-medium text-slate-700">Mật khẩu</label>
                                <input id="password" name="password" type="password" required placeholder="Nhập mật khẩu" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm outline-none transition focus:border-[#2e6144] focus:ring-4 focus:ring-[#2e6144]/10">
                            </div>

                            <div class="flex items-center justify-between gap-4 text-sm">
                                <label class="inline-flex items-center gap-2 text-slate-600">
                                    <input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-[#2e6144] focus:ring-[#2e6144]">
                                    Ghi nhớ đăng nhập
                                </label>
                                <a href="#" class="font-medium text-slate-600 transition hover:text-[#2e6144] hover:underline">Quên mật khẩu?</a>
                            </div>

                            <button type="submit" class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-[#2e6144] px-5 text-sm font-bold text-white shadow-lg shadow-[#2e6144]/20 transition hover:bg-[#224833] focus:outline-none">
                                Đăng nhập
                            </button>
                        </form>

                        <!-- FORM ĐĂNG KÝ -->
                        <form method="POST" action="{{ route('register.store') }}" class="space-y-3" x-show="!isLogin" style="display: none;">
                            @csrf
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-700">Tên đăng nhập</label>
                                <input type="text" name="username" required placeholder="Nhập username" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:border-[#2e6144]">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-700">Họ và tên (Full name)</label>
                                <input type="text" name="fullname" required placeholder="Nhập họ và tên" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:border-[#2e6144]">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-700">Email</label>
                                <input type="email" name="email" required placeholder="Nhập địa chỉ email" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:border-[#2e6144]">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-700">Số điện thoại</label>
                                <input type="text" name="phone" required placeholder="Nhập số điện thoại" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:border-[#2e6144]">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-700">Mật khẩu</label>
                                <input type="password" name="password" required placeholder="Tạo mật khẩu" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:border-[#2e6144]">
                            </div>

                            <button type="submit" class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-[#2e6144] px-5 text-sm font-bold text-white shadow-lg transition hover:bg-[#224833] mt-2">
                                Đăng ký tài khoản
                            </button>
                        </form>

                        <div class="mt-5 text-center text-sm text-slate-600">
                            <span x-show="isLogin">Chưa có tài khoản? <button @click="isLogin = false" class="font-bold text-[#2e6144] hover:underline">Đăng ký ngay</button></span>
                            <span x-show="!isLogin" style="display: none;">Đã có tài khoản? <button @click="isLogin = true" class="font-bold text-[#2e6144] hover:underline">Đăng nhập</button></span>
                        </div>

                        <footer class="mt-6 space-y-2 text-center text-xs text-slate-500">
                            <p>© 2026 The Daily Crust. All rights reserved.</p>
                            <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2">
                                <a href="#" class="hover:underline">Hỗ trợ kỹ thuật</a>
                                <a href="#" class="hover:underline">Chính sách bảo mật</a>
                            </div>
                        </footer>
                    </div>
                </section>
            </div>
        </main>
    </body>
</html>