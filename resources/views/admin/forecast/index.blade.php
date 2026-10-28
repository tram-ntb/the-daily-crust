@extends('layouts.system_layout')

@section('title', 'Dự báo nhu cầu - The Daily Crust')

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
            {{-- Chọn ngày bằng LỊCH: ngày đã có dự báo có chấm xanh; nút ‹ › nhảy từng ngày --}}
            <form method="GET" id="forecastDateForm" action="{{ route('admin.forecast.index') }}" class="flex items-center gap-1.5">
                <a href="{{ route('admin.forecast.index', ['date' => $date->copy()->subDay()->toDateString()]) }}" title="Ngày trước"
                   class="flex h-9 w-9 items-center justify-center rounded-lg border border-stone-300 bg-white text-stone-600 hover:bg-stone-50"><i class="fa-solid fa-chevron-left text-xs"></i></a>
                <div class="relative">
                    <i class="fa-regular fa-calendar pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-stone-400"></i>
                    <input id="forecastDate" name="date" type="text" value="{{ $date->toDateString() }}" readonly autocomplete="off"
                           class="w-40 cursor-pointer rounded-lg border border-stone-300 bg-white py-2 pl-9 pr-3 text-sm font-semibold text-stone-800 focus:border-mint-700 focus:outline-none">
                </div>
                <a href="{{ route('admin.forecast.index', ['date' => $date->copy()->addDay()->toDateString()]) }}" title="Ngày sau"
                   class="flex h-9 w-9 items-center justify-center rounded-lg border border-stone-300 bg-white text-stone-600 hover:bg-stone-50"><i class="fa-solid fa-chevron-right text-xs"></i></a>
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

    @if ($rows->isEmpty() && $restock->isEmpty())
        <div class="rounded-xl border border-dashed border-stone-300 bg-white p-10 text-center text-stone-500">
            <i class="fa-solid fa-chart-line mb-3 text-3xl text-mint-700/60"></i>
            <p class="font-medium">Chưa có dữ liệu dự báo cho ngày {{ $date->format('d/m/Y') }}.</p>
            <p class="mt-1 text-sm">Bấm “Chạy mô hình dự báo” hoặc chờ lịch tự động lúc 21:00.</p>
        </div>
    @else
        @if ($rows->isNotEmpty())
        <h2 class="text-lg font-bold text-mint-900"><i class="fa-solid fa-oven mr-1"></i> Hàng sản xuất và pha chế tại quán <span class="text-sm font-normal text-stone-500">· sản lượng cần làm trong ngày (đồ uống pha theo đơn dùng để chuẩn bị nguyên liệu)</span></h2>
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
                            <td class="px-4 py-2.5 text-stone-500">{{ $r['category'] }}@if ($r['made_to_order']) <span class="ml-1 rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-semibold text-sky-800">Pha theo đơn</span>@endif</td>
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

        {{-- Hàng nhập về: dự báo 7 ngày để Manager quyết định nhập thêm --}}
        @if ($restock->isNotEmpty())
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-mint-900"><i class="fa-solid fa-truck-ramp-box mr-1"></i> Hàng nhập từ nhà cung cấp <span class="text-sm font-normal text-stone-500">· hàng mua sẵn (nước suối đóng chai, hàng thương mại, quà tặng): gợi ý nhập cho 7 ngày tới</span></h2>
            <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-[#d8e8d2] text-left text-xs font-bold uppercase tracking-wider text-mint-900">
                        <tr>
                            <th class="px-4 py-3">Sản phẩm</th>
                            <th class="px-4 py-3">Nhóm</th>
                            <th class="px-4 py-3 text-right">Nhu cầu 7 ngày</th>
                            <th class="px-4 py-3 text-right">TB / ngày</th>
                            <th class="px-4 py-3 text-right">Tồn hiện tại</th>
                            <th class="px-4 py-3 text-right">Đủ bán (ngày)</th>
                            <th class="px-4 py-3 text-right">Gợi ý nhập thêm</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($restock as $r)
                            <tr class="hover:bg-stone-50">
                                <td class="px-4 py-2.5 font-medium text-stone-800">{{ $r['name'] }}</td>
                                <td class="px-4 py-2.5 text-stone-500">{{ $r['category'] }}</td>
                                <td class="px-4 py-2.5 text-right">{{ $r['demand'] }}</td>
                                <td class="px-4 py-2.5 text-right text-stone-500">{{ $r['per_day'] }}</td>
                                <td class="px-4 py-2.5 text-right">{{ $r['stock'] }}</td>
                                <td class="px-4 py-2.5 text-right {{ $r['cover_days'] !== null && $r['cover_days'] < 3 ? 'font-semibold text-red-700' : 'text-stone-600' }}">{{ $r['cover_days'] ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    @if ($r['suggest'] > 0)
                                        <span class="rounded-md bg-amber-100 px-2 py-1 text-base font-bold text-amber-800">+{{ $r['suggest'] }}</span>
                                    @else
                                        <span class="text-xs font-medium text-emerald-700">Đủ hàng</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-xs leading-relaxed text-stone-500">
                Gợi ý = nhu cầu 7 ngày × (1 + {{ round($safety * 100) }}% dự phòng) − tồn bán được hiện tại (lô còn hạn). Đây chỉ là số tham khảo, Manager quyết định số lượng nhập thực tế.
            </p>
        </div>
        @endif
    @endif
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<style>
    .flatpickr-day.has-forecast { position: relative; font-weight: 700; }
    .flatpickr-day.has-forecast::after { content: ''; position: absolute; bottom: 3px; left: 50%; width: 5px; height: 5px; margin-left: -2.5px; border-radius: 9999px; background: #3f6b3a; }
    .flatpickr-day.selected.has-forecast::after { background: #fff; }
    .flatpickr-day.selected { background: #3f6b3a; border-color: #3f6b3a; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/vn.js"></script>
<script>
    (function () {
        const hasForecast = new Set(@json($dates->values()));   // các ngày đã có dự báo (đánh chấm xanh)
        flatpickr('#forecastDate', {
            dateFormat: 'Y-m-d',            // giá trị gửi lên máy chủ
            altInput: true, altFormat: 'd/m/Y',   // giá trị hiển thị cho người dùng
            altInputClass: 'w-40 cursor-pointer rounded-lg border border-stone-300 bg-white py-2 pl-9 pr-3 text-sm font-semibold text-stone-800 focus:border-mint-700 focus:outline-none',
            defaultDate: @json($date->toDateString()),
            disableMobile: true,
            locale: Object.assign({}, (window.flatpickr && flatpickr.l10ns && flatpickr.l10ns.vn) || {}, { firstDayOfWeek: 1 }),
            onDayCreate: (sel, str, fp, el) => {
                if (hasForecast.has(fp.formatDate(el.dateObj, 'Y-m-d'))) el.classList.add('has-forecast');
            },
            onChange: (sel, str, fp) => { if (str) document.getElementById('forecastDateForm').submit(); },
        });
    })();
</script>
@endpush

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
