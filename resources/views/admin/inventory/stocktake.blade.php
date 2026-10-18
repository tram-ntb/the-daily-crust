@extends('layouts.system_layout')

@section('title', 'Kiểm kê kho (Stocktake) - The Daily Crust')

@section('content')
<div class="space-y-6">
    <!-- Tiêu đề -->
    <div class="bg-white p-6 rounded-2xl border border-stone-200 shadow-xs">
        <h1 class="text-2xl font-black text-stone-900">Kiểm kê kho định kỳ (Stocktake Sessions)</h1>
        <p class="text-sm text-stone-500 mt-1">Thực hiện kiểm kê tồn kho thực tế cuối ngày, đối soát chênh lệch và lập biên bản giải trình hao hụt nếu vượt dung sai.</p>
    </div>

    @if(session('success'))
        <div class="p-4 bg-emerald-100 text-emerald-800 rounded-xl font-bold text-sm">{{ session('success') }}</div>
    @endif

    <!-- Form tạo phiên kiểm kê kho -->
    <div class="bg-white rounded-2xl border border-stone-200 p-6 shadow-xs">
        <h2 class="text-lg font-bold text-stone-900 mb-4"><i class="fa-solid fa-clipboard-check text-mint-700 mr-2"></i> Lập phiên kiểm kê mới</h2>
        
        <form action="{{ route('admin.stocktake.store') }}" method="POST" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-stone-600 mb-1">Tên phiên kiểm kê</label>
                    <input type="text" name="session_name" value="Kiểm kê định kỳ cuối ngày {{ date('d/m/Y') }}" class="w-full px-3 py-2 border border-stone-300 rounded-xl text-sm" required>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-stone-600 mb-1">Ghi chú phiên</label>
                    <input type="text" name="note" placeholder="Ghi chú tổng quan..." class="w-full px-3 py-2 border border-stone-300 rounded-xl text-sm">
                </div>
            </div>

            <!-- Bảng kê khai số lượng thực tế các nguyên liệu -->
            <div class="mt-4 border border-stone-200 rounded-xl overflow-hidden">
                <div class="max-h-96 overflow-y-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-stone-100 sticky top-0">
                            <tr class="text-stone-700 text-xs uppercase font-bold">
                                <th class="p-3">Nguyên liệu</th>
                                <th class="p-3">Danh mục</th>
                                <th class="p-3">Tồn hệ thống</th>
                                <th class="p-3">Số lượng thực tế kiểm kê</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-200 text-sm bg-white">
                            @foreach($ingredients as $ing)
                            <tr>
                                <td class="p-3 font-semibold text-stone-800">{{ $ing->ingredient_name }}</td>
                                <td class="p-3 text-stone-500 text-xs">{{ $ing->category }}</td>
                                <td class="p-3 font-bold text-stone-700">{{ number_format($ing->stock_quantity, 0) }} {{ $ing->unit }}</td>
                                <td class="p-3">
                                    <div class="flex items-center space-x-2">
                                        <input type="number" step="0.1" name="items[{{ $ing->ingredient_id }}]" placeholder="Nhập thực tế..." class="w-40 px-3 py-1.5 border border-stone-300 rounded-lg text-sm">
                                        <span class="text-xs font-bold text-stone-500">{{ $ing->unit }}</span>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="px-6 py-2.5 bg-mint-700 hover:bg-mint-800 text-white font-bold rounded-xl text-sm shadow-xs transition">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> Hoàn tất & Gửi phê duyệt kiểm kê
                </button>
            </div>
        </form>
    </div>

    <!-- Danh sách các phiên kiểm kê đã lưu -->
    <div class="bg-white rounded-2xl border border-stone-200 p-6 shadow-xs">
        <h2 class="text-lg font-bold text-stone-900 mb-4"><i class="fa-solid fa-history text-amber-700 mr-2"></i> Lịch sử các phiên kiểm kê kho</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-stone-100 text-stone-700 text-xs uppercase font-bold">
                        <th class="p-3">Mã Phiên</th>
                        <th class="p-3">Thời gian</th>
                        <th class="p-3">Tên phiên</th>
                        <th class="p-3">Trạng thái</th>
                        <th class="p-3">Ghi chú</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200 text-sm">
                    @forelse($sessions as $s)
                    <tr>
                        <td class="p-3 font-bold">#{{ $s->stocktake_id }}</td>
                        <td class="p-3 text-stone-600">{{ $s->stocktake_date }}</td>
                        <td class="p-3 font-semibold text-stone-800">{{ $s->session_name }}</td>
                        <td class="p-3">
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $s->status === 'Pending_Approval' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                {{ $s->status }}
                            </span>
                        </td>
                        <td class="p-3 text-stone-600 text-xs">{{ $s->note }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="p-6 text-center text-stone-500">Chưa có phiên kiểm kê nào được tạo.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection