@extends('layouts.system_layout')

@section('title', 'Kế hoạch sản xuất tại bếp - The Daily Crust')

@section('content')
<div class="space-y-6">
    <!-- Tiêu đề phân hệ -->
    <div class="bg-white p-6 rounded-2xl border border-stone-200 shadow-xs flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-black text-stone-900">Quản lý Sản xuất tại Bếp</h1>
            <p class="text-sm text-stone-500 mt-1">Tiếp nhận kế hoạch sản xuất đầu ngày và ghi nhận mẻ nướng thủ công.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 bg-emerald-100 text-emerald-800 rounded-xl font-bold text-sm">{{ session('success') }}</div>
    @endif

    <!-- KHỐI KẾ HOẠCH SẢN XUẤT ĐẦU NGÀY -->
    <div class="bg-white rounded-2xl border border-stone-200 p-6 shadow-xs">
        <h2 class="text-lg font-bold text-stone-900 mb-4"><i class="fa-solid fa-industry text-mint-700 mr-2"></i> Tiếp nhận Kế hoạch sản xuất & Ghi nhận mẻ nướng</h2>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-stone-100 text-stone-700 text-xs uppercase font-bold">
                        <th class="p-3">Mã Kế Hoạch</th>
                        <th class="p-3">Sản phẩm</th>
                        <th class="p-3">Số lượng kế hoạch</th>
                        <th class="p-3">Trạng thái</th>
                        <th class="p-3">Thao tác ghi nhận mẻ nướng</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200 text-sm">
                    @isset($plans)
                        @forelse($plans as $plan)
                        <tr>
                            <td class="p-3 font-bold">#{{ $plan->plan_id }}</td>
                            <td class="p-3 font-semibold text-stone-800">{{ $plan->product->vi_name ?? 'N/A' }}</td>
                            <td class="p-3">{{ $plan->planned_qty ?? $plan->adjusted_qty }} cái</td>
                            <td class="p-3">
                                <form action="{{ route('admin.production.plan.accept') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="plan_id" value="{{ $plan->plan_id }}">
                                    <button type="submit" {{ $plan->plan_status === 'Completed' ? 'disabled' : '' }} class="rounded-lg px-3 py-1 text-xs font-bold disabled:cursor-not-allowed disabled:opacity-60 {{ $plan->plan_status === 'Accepted' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $plan->plan_status === 'Accepted' ? 'Đã tiếp nhận' : ($plan->plan_status === 'Completed' ? 'Đã hoàn tất' : 'Tiếp nhận kế hoạch') }}
                                    </button>
                                </form>
                            </td>
                            <td class="p-3">
                                <form action="{{ route('admin.production.batch.store') }}" method="POST" class="flex items-center space-x-2">
                                    @csrf
                                    <input type="hidden" name="plan_id" value="{{ $plan->plan_id }}">
                                    <input type="number" name="passed_qty" placeholder="SL Đạt" class="w-24 px-2 py-1 border border-stone-300 rounded-lg text-sm" min="0" required>
                                    <input type="number" name="failed_qty" placeholder="SL Hỏng" class="w-24 px-2 py-1 border border-stone-300 rounded-lg text-sm" min="0" required>
                                    <button type="submit" {{ $plan->plan_status !== 'Accepted' ? 'disabled' : '' }} class="px-3 py-1 bg-mint-700 hover:bg-mint-800 disabled:cursor-not-allowed disabled:opacity-50 text-white font-bold rounded-lg text-xs shadow-xs">Xác nhận nướng</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="p-4 text-center text-stone-500">Không có kế hoạch sản xuất nào cho ngày hôm nay.</td>
                        </tr>
                        @endforelse
                    @endisset
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection