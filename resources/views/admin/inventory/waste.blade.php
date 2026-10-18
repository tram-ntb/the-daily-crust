@extends('layouts.system_layout')

@section('title', 'Quản lý Tiêu hủy Hàng hỏng - The Daily Crust')

@section('content')
<div class="space-y-6">
    <!-- Tiêu đề -->
    <div class="bg-white p-6 rounded-2xl border border-stone-200 shadow-xs flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-2xl font-black text-stone-900">Quản lý Tiêu hủy Hàng hỏng (Inventory Waste)</h1>
            <p class="text-sm text-stone-500 mt-1">Ghi nhận và lập phiếu tiêu hủy đối với sản phẩm quá hạn, lỗi hỏng hoặc nguyên liệu thất thoát.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 bg-emerald-100 text-emerald-800 rounded-xl font-bold text-sm">{{ session('success') }}</div>
    @endif

    <!-- Form tạo phiếu tiêu hủy mới -->
    <div class="bg-white rounded-2xl border border-stone-200 p-6 shadow-xs" x-data="{ itemType: 'Product' }">
        <h2 class="text-lg font-bold text-stone-900 mb-4"><i class="fa-solid fa-triangle-exclamation text-rose-600 mr-2"></i> Lập phiếu tiêu hủy mới</h2>
        
        <form action="{{ route('admin.inventory.waste.store') }}" method="POST" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-stone-600 mb-1">Loại đối tượng</label>
                    <select name="item_type" x-model="itemType" class="w-full px-3 py-2 border border-stone-300 rounded-xl text-sm focus:ring-2 focus:ring-mint-700" required>
                        <option value="Product">Thành phẩm (Bánh/Đồ uống)</option>
                        <option value="Ingredient">Nguyên liệu thô</option>
                    </select>
                </div>

                <div x-show="itemType === 'Product'">
                    <label class="block text-xs font-bold uppercase text-stone-600 mb-1">Chọn Sản phẩm</label>
                    <select name="product_id" class="w-full px-3 py-2 border border-stone-300 rounded-xl text-sm">
                        <option value="">-- Chọn sản phẩm --</option>
                        @foreach($products as $p)
                            <option value="{{ $p->product_id }}">{{ $p->vi_name }} ({{ $p->item }})</option>
                        @endforeach
                    </select>
                </div>

                <div x-show="itemType === 'Ingredient'">
                    <label class="block text-xs font-bold uppercase text-stone-600 mb-1">Chọn Nguyên liệu</label>
                    <select name="ingredient_id" class="w-full px-3 py-2 border border-stone-300 rounded-xl text-sm">
                        <option value="">-- Chọn nguyên liệu --</option>
                        @foreach($ingredients as $i)
                            <option value="{{ $i->ingredient_id }}">{{ $i->ingredient_name }} ({{ $i->unit }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-stone-600 mb-1">Số lượng hỏng</label>
                    <input type="number" step="0.1" name="quantity" placeholder="Nhập số lượng..." class="w-full px-3 py-2 border border-stone-300 rounded-xl text-sm" required>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-stone-600 mb-1">Lý do tiêu hủy / Nguyên nhân lỗi</label>
                <input type="text" name="reason_type" placeholder="Ví dụ: Lỗi nướng, quá hạn sử dụng, rơi vãi..." class="w-full px-3 py-2 border border-stone-300 rounded-xl text-sm" required>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-5 py-2 bg-rose-700 hover:bg-rose-800 text-white font-bold rounded-xl text-sm shadow-xs transition">
                    <i class="fa-solid fa-ban mr-1"></i> Xác nhận lập phiếu tiêu hủy
                </button>
            </div>
        </form>
    </div>

    <!-- Bảng danh sách phiếu tiêu hủy đã lập -->
    <div class="bg-white rounded-2xl border border-stone-200 p-6 shadow-xs">
        <h2 class="text-lg font-bold text-stone-900 mb-4"><i class="fa-solid fa-rectangle-list text-mint-700 mr-2"></i> Lịch sử phiếu tiêu hủy gần đây</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-stone-100 text-stone-700 text-xs uppercase font-bold">
                        <th class="p-3">Mã Phiếu</th>
                        <th class="p-3">Thời gian</th>
                        <th class="p-3">Loại</th>
                        <th class="p-3">Tên mục</th>
                        <th class="p-3">Số lượng</th>
                        <th class="p-3">Lý do</th>
                        <th class="p-3">Tổng giá trị thất thoát</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200 text-sm">
                    @forelse($wastes as $w)
                    <tr>
                        <td class="p-3 font-bold">#{{ $w->ticket_id }}</td>
                        <td class="p-3 text-stone-600">{{ $w->ticket_date }}</td>
                        <td class="p-3">
                            <span class="px-2 py-1 bg-stone-100 text-stone-800 rounded text-xs font-bold">{{ $w->item_type }}</span>
                        </td>
                        <td class="p-3 font-semibold text-stone-800">
                            {{ $w->item_type === 'Product' ? ($w->product->vi_name ?? 'N/A') : ($w->ingredient->ingredient_name ?? 'N/A') }}
                        </td>
                        <td class="p-3 font-bold text-rose-600">{{ number_format($w->quantity, 1) }} {{ $w->unit }}</td>
                        <td class="p-3 text-stone-600">{{ $w->reason_type }}</td>
                        <td class="p-3 font-extrabold text-amber-800">{{ number_format($w->total_cost, 0, ',', '.') }} đ</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-6 text-center text-stone-500">Chưa có bản ghi tiêu hủy hàng hỏng nào.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $wastes->links() }}
        </div>
    </div>
</div>
@endsection