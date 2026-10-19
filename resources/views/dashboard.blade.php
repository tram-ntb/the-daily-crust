@extends('layouts.system_layout')

@section('title', 'Trang chủ - The Daily Crust')

@section('content')
    @php
        $role = Auth::user()->role ?? 'customer';
    @endphp

    @if($role === 'customer')
        <div class="space-y-8">
        
        <!-- BANNER GIỚI THIỆU -->
        <div class="bg-gradient-to-r from-mint-700 to-mint-800 rounded-2xl p-8 text-white shadow-md flex flex-col md:flex-row items-center justify-between">
            <div class="max-w-xl mb-6 md:mb-0">
                <span class="bg-amber-600 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">Tiệm bánh thủ công mỗi ngày</span>
                <h1 class="text-3xl md:text-4xl font-extrabold mt-3 tracking-tight">The Daily Crust</h1>
                <p class="text-stone-200 mt-3 text-base leading-relaxed">
                    Chúng tôi mang đến những mẻ bánh mì tươi ngon, bánh ngọt ngàn lớp thơm bơ và các loại đồ uống đậm vị được làm từ nguyên liệu chọn lọc khắt khe nhất.
                </p>
            </div>
            <div class="w-full md:w-1/3 flex justify-center">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="w-36 h-36 object-cover rounded-full border-4 border-white/20 shadow-xl bg-white p-2">
            </div>
        </div>

        <!-- DANH SÁCH SẢN PHẨM -->
        <div id="products-section">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-stone-900">
                        @if(request('search'))
                            Kết quả tìm kiếm cho: "{{ request('search') }}"
                        @elseif(request('category'))
                            Danh mục: {{ request('category') }}
                        @else
                            Tất cả sản phẩm
                        @endif
                    </h2>
                    <p class="text-sm text-stone-500">Sản phẩm tươi ngon luôn sẵn sàng phục vụ quý khách</p>
                </div>
                @if(request('category') || request('search') || request('price_from') || request('sort'))
                    <a href="{{ url('/') }}" class="text-sm bg-stone-200 text-stone-800 px-4 py-2 rounded-lg hover:bg-stone-300 font-bold">Xóa bộ lọc</a>
                @endif
            </div>

            <!-- Lưới hiển thị sản phẩm -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                @forelse($products as $product)
                <div class="bg-white rounded-xl border border-stone-200 shadow-xs hover:shadow-md transition flex flex-col relative group" x-data="{ liked: false }">
                    
                    <!-- Nút Trái tim: Kiểm tra nếu chưa đăng nhập thì hiện thông báo cần đăng nhập -->
                    @auth
                        <button @click="liked = !liked" class="absolute top-3 right-3 z-10 w-9 h-9 rounded-full bg-white/90 backdrop-blur-xs flex items-center justify-center shadow-md transition hover:scale-110 focus:outline-none">
                            <i x-show="!liked" class="fa-regular fa-heart text-lg text-stone-600"></i>
                            <i x-show="liked" class="fa-solid fa-heart text-lg text-red-500"></i>
                        </button>
                    @else
                        <button onclick="alert('Vui lòng đăng nhập để thêm sản phẩm vào danh sách yêu thích!');" class="absolute top-3 right-3 z-10 w-9 h-9 rounded-full bg-white/90 backdrop-blur-xs flex items-center justify-center shadow-md transition hover:scale-110 focus:outline-none">
                            <i class="fa-regular text-stone-600 fa-heart text-lg"></i>
                        </button>
                    @endauth

                    <div class="h-48 bg-stone-100 rounded-t-xl relative overflow-hidden flex items-center justify-center">
                        @if($product->image_url && file_exists(public_path($product->image_url)))
                            <img src="{{ asset($product->image_url) }}" alt="{{ $product->vi_name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                        @else
                            <div class="text-stone-400 flex flex-col items-center">
                                <i class="fa-solid fa-bread-slice text-4xl mb-2 text-stone-300"></i>
                                <span class="text-xs">The Daily Crust</span>
                            </div>
                        @endif
                    </div>

                    <!-- Thông tin sản phẩm -->
                    <div class="p-4 flex-1 flex flex-col justify-between">
                        <div>
                            <h3 class="font-black text-stone-900 text-lg leading-snug group-hover:text-mint-700 transition">
                                {{ $product->item }}
                            </h3>
                            <p class="text-sm font-semibold text-stone-700 mt-0.5">
                                {{ $product->vi_name }}
                            </p>
                            <p class="text-xs text-stone-500 mt-1.5 line-clamp-2">
                                {{ $product->description ?? 'Bánh thủ công thơm ngon từ nguyên liệu tự nhiên.' }}
                            </p>
                        </div>

                        <div class="mt-4 pt-3 border-t border-stone-100 flex items-center justify-between">
                            <span class="font-extrabold text-amber-800 text-lg">
                                {{ number_format($product->price, 0, ',', '.') }} đ
                            </span>
                            @auth
                                <button type="button"
                                        @click="$store.cart.add({ id: {{ $product->product_id }}, name: @js($product->vi_name), sub: @js($product->item), price: {{ (int) $product->price }}, image: @js($product->image_url ? asset($product->image_url) : null) })"
                                        class="w-10 h-10 rounded-lg bg-emerald-50 text-mint-700 hover:bg-mint-700 hover:text-white transition flex items-center justify-center shadow-xs" title="Thêm vào giỏ hàng">
                                    <i class="fa-solid fa-cart-plus text-base"></i>
                                </button>
                            @else
                                <button onclick="alert('Vui lòng đăng nhập để thêm sản phẩm vào giỏ hàng!')" class="w-10 h-10 rounded-lg bg-emerald-50 text-mint-700 hover:bg-mint-700 hover:text-white transition flex items-center justify-center shadow-xs">
                                    <i class="fa-solid fa-cart-shopping text-base"></i>
                                </button>
                            @endauth
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-span-full py-12 text-center text-stone-500 text-lg">
                    Không tìm thấy sản phẩm nào phù hợp với yêu cầu của bạn.
                </div>
                @endforelse
            </div>

            @if($products->hasPages())
                <div class="mt-10 flex flex-col items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm sm:flex-row sm:p-5">
                    <p class="text-sm text-stone-500">
                        Hiển thị
                        <span class="font-bold text-stone-800">{{ $products->firstItem() }}–{{ $products->lastItem() }}</span>
                        trong tổng số
                        <span class="font-bold text-stone-800">{{ $products->total() }}</span> sản phẩm
                    </p>

                    <nav class="flex items-center gap-1" aria-label="Phân trang sản phẩm">
                        @if($products->onFirstPage())
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-stone-200 text-stone-300" aria-hidden="true">
                                <i class="fa-solid fa-chevron-left text-xs"></i>
                            </span>
                        @else
                            <a href="{{ $products->previousPageUrl() }}" class="flex h-10 w-10 items-center justify-center rounded-xl border border-stone-200 text-stone-600 transition hover:border-mint-700 hover:bg-mint-700 hover:text-white" aria-label="Trang trước">
                                <i class="fa-solid fa-chevron-left text-xs"></i>
                            </a>
                        @endif

                        @foreach($products->getUrlRange(max(1, $products->currentPage() - 2), min($products->lastPage(), $products->currentPage() + 2)) as $page => $url)
                            @if($page == $products->currentPage())
                                <span class="flex h-10 min-w-10 items-center justify-center rounded-xl bg-mint-700 px-3 font-bold text-white shadow-sm" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="flex h-10 min-w-10 items-center justify-center rounded-xl border border-stone-200 px-3 font-semibold text-stone-600 transition hover:border-mint-700 hover:bg-mint-700 hover:text-white">{{ $page }}</a>
                            @endif
                        @endforeach

                        @if($products->hasMorePages())
                            <a href="{{ $products->nextPageUrl() }}" class="flex h-10 w-10 items-center justify-center rounded-xl border border-stone-200 text-stone-600 transition hover:border-mint-700 hover:bg-mint-700 hover:text-white" aria-label="Trang sau">
                                <i class="fa-solid fa-chevron-right text-xs"></i>
                            </a>
                        @else
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-stone-200 text-stone-300" aria-hidden="true">
                                <i class="fa-solid fa-chevron-right text-xs"></i>
                            </span>
                        @endif
                    </nav>
                </div>
            @endif
        </div>

        </div>

        {{-- Giỏ hàng & đặt hàng online (chỉ khi đã đăng nhập) --}}
        @auth
            @include('customer._cart')
        @endauth
    @endif
@endsection