@extends('layouts.system_layout')
 
@section('title', ($isManager ? 'Tổng quan Quản lý' : 'Tổng quan Nhân viên') . ' - The Daily Crust')
 
@section('content')
@php
    $levelStyle = [
        'danger'  => ['box' => 'bg-red-50 border-red-200 text-red-800',     'btn' => 'text-red-700'],
        'warning' => ['box' => 'bg-amber-50 border-amber-200 text-amber-800','btn' => 'text-amber-800'],
        'info'    => ['box' => 'bg-sky-50 border-sky-200 text-sky-800',     'btn' => 'text-sky-800'],
    ];
    $btnStyle = [
        'primary' => 'bg-mint-700 text-white hover:bg-mint-800 border border-mint-700',
        'soft'    => 'bg-[#d8e8d2] text-mint-800 hover:bg-[#c9ddc2] border border-mint-700/30',
        'outline' => 'bg-white text-stone-800 hover:bg-stone-100 border border-stone-300',
    ];
@endphp
 
<div class="space-y-6">
 
    {{-- Tiêu đề --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-stone-900">{{ $isManager ? 'Tổng quan Quản lý' : 'Tổng quan Nhân viên' }}</h1>
            <p class="text-sm text-stone-500 mt-1">
                Xin chào {{ Auth::user()->full_name ?? Auth::user()->username }} ·
                số liệu ngày <span class="font-semibold text-stone-700">{{ $bizDate->format('d/m/Y') }}</span>
            </p>
        </div>
        <span class="text-xs font-bold uppercase tracking-wider px-3 py-1.5 rounded-full {{ $isManager ? 'bg-mint-700 text-white' : 'bg-[#d8e8d2] text-mint-800' }}">
            <i class="fa-solid fa-shield-halved mr-1"></i>{{ $isManager ? 'Quản lý' : 'Nhân viên' }}
        </span>
    </div>
 
    @unless($isToday)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800">
            <i class="fa-solid fa-circle-info mr-2"></i>
            Hôm nay chưa có giao dịch nên đang hiển thị ngày gần nhất có dữ liệu ({{ $bizDate->format('d/m/Y') }}).
        </div>
    @endunless
 
    {{-- KHỐI 1: KPI --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($kpis as $k)
            <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-semibold text-stone-500">{{ $k['label'] }}</p>
                <p class="mt-2 text-2xl font-extrabold text-stone-900 tabular-nums">{{ $k['value'] }}</p>
                <p class="mt-1 text-xs text-stone-500">
                    @if($k['growth'] !== null)
                        <span class="font-bold {{ $k['growth'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                            <i class="fa-solid {{ $k['growth'] >= 0 ? 'fa-caret-up' : 'fa-caret-down' }}"></i>
                            {{ $k['growth'] >= 0 ? '+' : '' }}{{ number_format($k['growth'], 1, ',', '.') }}%
                        </span>
                        · {{ $k['sub'] }}
                    @else
                        {{ $k['sub'] }}
                    @endif
                </p>
            </div>
        @endforeach
    </div>
 
    {{-- KHỐI 2: Cảnh báo & việc cần xử lý --}}
    <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-lg font-bold text-stone-900">Cảnh báo &amp; Công việc cần xử lý</h2>
            @if(!$isManager)
                <span class="text-xs text-stone-500">Chế độ chỉ xem — báo quản lý để xử lý</span>
            @endif
        </div>
 
        <div class="space-y-2">
            @forelse($alerts as $a)
                @php $s = $levelStyle[$a['level']] ?? $levelStyle['info']; @endphp
                <div class="rounded-lg border {{ $s['box'] }}" x-data="{ open: false }">
                    <div class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm font-medium">
                        <span class="flex items-center min-w-0">
                            <i class="fa-solid {{ $a['icon'] }} w-6 shrink-0"></i>
                            <span class="truncate">{{ $a['title'] }}</span>
                        </span>
                        <span class="flex items-center gap-3 shrink-0 text-xs">
                            @if(!empty($a['items']))
                                <button type="button" @click="open = !open" class="underline">
                                    <span x-text="open ? 'Thu gọn' : 'Chi tiết'"></span>
                                </button>
                            @endif
                            @if($a['url'] !== '#')
                                <a href="{{ $a['url'] }}" class="font-bold underline {{ $s['btn'] }}">{{ $a['cta'] }}</a>
                            @endif
                        </span>
                    </div>
 
                    @if(!empty($a['items']))
                        <ul x-show="open" x-cloak class="border-t border-black/5 bg-white/60 px-4 py-2 text-sm text-stone-800 space-y-2">
                            @foreach($a['items'] as $it)
                                <li>
                                    <div class="flex justify-between gap-3">
                                        <span class="font-semibold">{{ $it['name'] }}</span>
                                        <span class="tabular-nums text-stone-600">{{ $it['text'] }}</span>
                                    </div>
                                    <div class="mt-1 h-1.5 w-full rounded-full bg-stone-200">
                                        <div class="h-1.5 rounded-full bg-red-600" style="width: {{ $it['ratio'] }}%"></div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @empty
                <p class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm font-medium text-emerald-800">
                    <i class="fa-solid fa-circle-check mr-2"></i>Không có cảnh báo nào cần xử lý.
                </p>
            @endforelse
        </div>
    </div>
 
    {{-- KHỐI 3: Biểu đồ --}}
    <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
        <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm xl:col-span-2">
            <h2 class="text-lg font-bold text-stone-900">Dự báo sản xuất vs Bán hàng thực tế (theo giờ)</h2>
            <p class="text-xs text-stone-500">Đơn vị: số bánh/giờ</p>
            <div class="mt-3 h-72"><canvas id="chartHourly" aria-label="Biểu đồ dự báo và thực tế theo giờ" role="img"></canvas></div>
            @if($hourlyChart['source'])
                <p class="mt-2 text-xs text-stone-500"><i class="fa-solid fa-circle-info mr-1"></i>Nguồn dự báo: {{ $hourlyChart['source'] }}</p>
            @else
                <p class="mt-2 text-xs text-amber-700"><i class="fa-solid fa-circle-info mr-1"></i>Chưa có dữ liệu để dự báo theo giờ.</p>
            @endif
            @if($hourlyChart['model'])
                <p class="text-xs text-stone-500"><i class="fa-solid fa-robot mr-1"></i>{{ $hourlyChart['model'] }}</p>
            @endif
        </div>
 
        <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-stone-900">
                {{ $categoryChart['metric'] === 'revenue' ? 'Doanh thu theo dòng sản phẩm' : 'Số lượng bán theo dòng sản phẩm' }}
            </h2>
            <p class="text-xs text-stone-500">{{ $categoryChart['metric'] === 'revenue' ? 'Đơn vị: nghìn đồng' : 'Đơn vị: chiếc' }}</p>
            <div class="mt-3 h-72">
                @if(count($categoryChart['values']))
                    <canvas id="chartCategory" aria-label="Biểu đồ theo dòng sản phẩm" role="img"></canvas>
                @else
                    <p class="flex h-full items-center justify-center text-sm text-stone-500">Chưa có số liệu trong ngày.</p>
                @endif
            </div>
        </div>
    </div>
 
    {{-- KHỐI 4: Lối tắt hành động nhanh --}}
    <div>
        <h2 class="mb-3 text-lg font-bold text-stone-900">Lối tắt nhanh</h2>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 {{ count($quickActions) >= 4 ? 'xl:grid-cols-4' : 'xl:grid-cols-3' }}">
            @foreach($quickActions as $qa)
                @php $cls = 'flex items-center justify-center rounded-lg px-4 py-3 text-sm font-bold shadow-sm transition ' . $btnStyle[$qa['style']]; @endphp
                @if($qa['url'] === '#')
                    <button type="button" class="{{ $cls }} opacity-70" onclick="alert('Chức năng này đang được phát triển.')">
                        <i class="fa-solid {{ $qa['icon'] }} mr-2"></i>{{ $qa['label'] }}
                    </button>
                @elseif($qa['method'] === 'post')
                    <form method="POST" action="{{ $qa['url'] }}" onsubmit="return confirm('{{ $qa['label'] }}?')">
                        @csrf
                        <button type="submit" class="{{ $cls }} w-full">
                            <i class="fa-solid {{ $qa['icon'] }} mr-2"></i>{{ $qa['label'] }}
                        </button>
                    </form>
                @else
                    <a href="{{ $qa['url'] }}" class="{{ $cls }}">
                        <i class="fa-solid {{ $qa['icon'] }} mr-2"></i>{{ $qa['label'] }}
                    </a>
                @endif
            @endforeach
        </div>
    </div>
</div>
@endsection
 
@push('styles')
<style>[x-cloak]{display:none !important}</style>
@endpush
 
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const hourly   = @json($hourlyChart);
    const category = @json($categoryChart);
    const green = '#2e6144', lightGreen = '#7fae8d', grid = 'rgba(0,0,0,.06)';
 
    Chart.defaults.font.family = "'Times New Roman', Times, serif";
    Chart.defaults.color = '#57534e';
 
    // Dự báo (nét đứt, nhạt) vs thực tế (nét liền, đậm): phân biệt bằng kiểu nét, không chỉ bằng màu
    new Chart(document.getElementById('chartHourly'), {
        type: 'line',
        data: {
            labels: hourly.labels,
            datasets: [
                { label: 'Sản lượng dự báo', data: hourly.forecast, borderColor: lightGreen, borderDash: [6, 4],
                  borderWidth: 2, tension: 0.35, pointRadius: 0, pointHoverRadius: 5, fill: false },
                { label: 'Sản lượng bán thực tế', data: hourly.actual, borderColor: green, backgroundColor: 'rgba(46,97,68,.10)',
                  borderWidth: 2, tension: 0.35, pointRadius: 0, pointHoverRadius: 5, fill: true }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, grid: { color: grid }, ticks: { precision: 0 } }
            }
        }
    });
 
    const catCanvas = document.getElementById('chartCategory');
    if (catCanvas) {
        const isRevenue = category.metric === 'revenue';
        // Một chỉ số, một màu: thanh ngang đơn sắc, xếp giảm dần
        new Chart(catCanvas, {
            type: 'bar',
            data: {
                labels: category.labels,
                datasets: [{ data: category.values.map(v => isRevenue ? Math.round(v / 1000) : v),
                             backgroundColor: green, borderRadius: 4, maxBarThickness: 22 }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: c => isRevenue
                        ? ' ' + category.values[c.dataIndex].toLocaleString('vi-VN') + ' đ'
                        : ' ' + c.parsed.x.toLocaleString('vi-VN') + ' chiếc' } }
                },
                scales: {
                    x: { beginAtZero: true, grid: { color: grid }, ticks: { precision: 0 } },
                    y: { grid: { display: false } }
                }
            }
        });
    }
});
</script>
@endpush