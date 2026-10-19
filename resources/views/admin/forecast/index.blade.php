@extends('layouts.system_layout')

@section('title', 'Dự báo nhu cầu sản xuất - The Daily Crust')

@section('content')
<div class="space-y-6">

    {{-- Tiêu đề + chọn ngày + nút chạy --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-mint-900">
                Kế hoạch sản xuất dự báo ngày {{ $date->format('d/m/Y') }}
            </h1>
            <p class="mt-1 text-sm text-stone-500">
                @if ($model)
                    Mô hình <span class="font-semibold text-mint-800">{{ $model }}</span>
                    · tạo lúc {{ $createdAt?->format('H:i d/m/Y') }}
                    @if ($isTomorrow) · <span class="font-semibold text-mint-700">Ngày mai</span> @endif
                @else
                    Chưa có dự báo cho ngày này.
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('admin.forecast.index') }}" class="flex items-center gap-2">
                <select name="date" onchange="this.form.submit()"
                        class="rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm focus:border-mint-700 focus:outline-none">
                    @forelse ($dates as $d)
                        <option value="{{ $d }}" @selected($d === $date->toDateString())>
                            {{ \Carbon\Carbon::parse($d)->format('d/m/Y') }}
                        </option>
                    @empty
                        <option value="{{ $date->toDateString() }}">{{ $date->format('d/m/Y') }}</option>
                    @endforelse
                </select>
            </form>

            @if (Route::has('admin.forecast.run'))
                <form method="POST" action="{{ route('admin.forecast.run') }}"
                      x-data="{ busy: false }" @submit="busy = true">
                    @csrf
                    <button type="submit" :disabled="busy"
                            class="inline-flex items-center gap-2 rounded-lg bg-mint-700 px-4 py-2 text-sm font-semibold text-white hover:bg-mint-800 disabled:opacity-60">
                        <i class="fa-solid fa-chart-line" x-show="!busy"></i>
                        <i class="fa-solid fa-spinner fa-spin" x-show="busy" x-cloak></i>
                        <span x-text="busy ? 'Đang chạy mô hình...' : 'Chạy mô hình dự báo'"></span>
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if (session('success'))
        <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</p>
    @endif
    @if (session('error'))
        <p class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{{ session('error') }}</p>
    @endif

    @if ($rows->isEmpty())
        <div class="rounded-xl border border-dashed border-stone-300 bg-white p-10 text-center text-stone-500">
            <i class="fa-solid fa-chart-line mb-3 text-3xl text-mint-700/60"></i>
            <p class="font-medium">Chưa có dữ liệu dự báo cho ngày {{ $date->format('d/m/Y') }}.</p>
            <p class="mt-1 text-sm">Bấm “Chạy mô hình dự báo” hoặc chờ lịch tự động lúc 21:00.</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            {{-- Biểu đồ --}}
            <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm xl:col-span-2">
                <div class="mb-3 flex items-baseline justify-between">
                    <h2 class="font-semibold text-mint-900">Top 12 sản phẩm cần sản xuất</h2>
                    <span class="text-sm text-stone-500">Tổng: <b class="text-mint-800">{{ number_format($total) }}</b> cái</span>
                </div>
                <div class="h-72"><canvas id="forecastChart"></canvas></div>
            </div>

            {{-- Tóm tắt --}}
            <div class="space-y-3 rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-mint-900">Tóm tắt</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-stone-500">Số loại sản phẩm</dt><dd class="font-semibold">{{ $rows->count() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">Tổng sản lượng dự báo</dt><dd class="font-semibold">{{ number_format($total) }} cái</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">Độ tin cậy trung bình</dt><dd class="font-semibold">{{ number_format($rows->avg('confidence') * 100, 0) }}%</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">Đã có kế hoạch</dt><dd class="font-semibold">{{ $rows->whereNotNull('plan')->count() }}/{{ $rows->count() }}</dd></div>
                </dl>
                <p class="rounded-lg bg-stone-50 p-3 text-xs leading-relaxed text-stone-500">
                    “Độ tin cậy” là ước lượng thực nghiệm từ sai số khi kiểm định mô hình trên dữ liệu lịch sử, không phải xác suất thống kê.
                </p>
            </div>
        </div>

        {{-- Bảng chi tiết --}}
        <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-[#d8e8d2] text-left text-xs font-bold uppercase tracking-wider text-mint-900">
                    <tr>
                        <th class="px-4 py-3">Sản phẩm</th>
                        <th class="px-4 py-3">Nhóm</th>
                        <th class="px-4 py-3 text-right">Dự báo (cái)</th>
                        <th class="px-4 py-3 text-right">Kế hoạch đã lập</th>
                        <th class="px-4 py-3">Độ tin cậy</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($rows as $r)
                        <tr class="hover:bg-stone-50">
                            <td class="px-4 py-2.5 font-medium text-stone-800">{{ $r['name'] }}</td>
                            <td class="px-4 py-2.5 text-stone-500">{{ $r['category'] }}</td>
                            <td class="px-4 py-2.5 text-right text-base font-bold text-mint-800">{{ $r['forecast'] }}</td>
                            <td class="px-4 py-2.5 text-right">
                                @if ($r['plan'] === null)
                                    <span class="text-stone-400">—</span>
                                @else
                                    <span class="{{ $r['plan'] < $r['forecast'] ? 'text-amber-700' : 'text-stone-700' }}">{{ $r['plan'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <div class="h-1.5 w-24 rounded-full bg-stone-200">
                                        <div class="h-1.5 rounded-full bg-mint-700" style="width: {{ round($r['confidence'] * 100) }}%"></div>
                                    </div>
                                    <span class="text-xs text-stone-500">{{ number_format($r['confidence'] * 100, 0) }}%</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection

@push('scripts')
@if ($rows->isNotEmpty())
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    new Chart(document.getElementById('forecastChart'), {
        type: 'bar',
        data: {
            labels: @json($chart['labels']),
            datasets: [{ label: 'Sản lượng dự báo (cái)', data: @json($chart['data']), backgroundColor: '#3f6b3a', borderRadius: 4 }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
</script>
@endif
@endpush
