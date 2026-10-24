@extends('layouts.system_layout')

@section('title', 'Công thức '.$product->vi_name.' - The Daily Crust')

@section('content')
<div x-data="bomPage(@js([
        'lines' => $lines,
        'ingredients' => $ingredients,
        'price' => (int) $product->price,
        'canEdit' => $canEdit,
        'oldLines' => old('lines'),
    ]))"
     class="space-y-5">

    {{-- Đường dẫn + tiêu đề --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm text-stone-500"><a href="{{ route('admin.bom.index') }}" class="font-semibold text-mint-700 hover:underline">Công thức (BOM)</a> / <span>{{ $product->vi_name }}</span></p>
            <h1 class="mt-1 text-2xl font-bold text-mint-900">{{ $product->vi_name }}</h1>
        </div>
        @if ($canEdit)
            <div class="flex items-center gap-2">
                <template x-if="!editing">
                    <button type="button" @click="startEdit()" class="rounded-lg bg-mint-700 px-4 py-2 text-sm font-semibold text-white hover:bg-mint-800"><i class="fa-solid fa-pen mr-1"></i>Chỉnh sửa công thức</button>
                </template>
            </div>
        @endif
    </div>

    @include('staff.partials.flash')

    {{-- Thông tin chung (giống màn hình Bills of Materials) --}}
    <div class="grid grid-cols-1 gap-4 rounded-xl border border-stone-200 bg-white p-5 shadow-sm md:grid-cols-2">
        <dl class="space-y-3 text-sm">
            <div class="flex items-center gap-4">
                <dt class="w-32 font-bold text-stone-700">Sản phẩm</dt>
                <dd class="flex items-center gap-3">
                    <span class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-lg bg-stone-100">
                        @if ($product->image_url && file_exists(public_path($product->image_url)))
                            <img src="{{ asset($product->image_url) }}" alt="" class="h-full w-full object-cover">
                        @else
                            <i class="fa-solid fa-bread-slice text-xl text-stone-300"></i>
                        @endif
                    </span>
                    <span><span class="font-semibold text-stone-900">[{{ str_pad((string) $product->product_id, 4, '0', STR_PAD_LEFT) }}] {{ $product->vi_name }}</span><span class="block text-xs text-stone-400">{{ $product->item }} · {{ $product->category }}</span></span>
                </dd>
            </div>
            <div class="flex items-center gap-4">
                <dt class="w-32 font-bold text-stone-700">Số lượng</dt>
                <dd class="font-semibold text-stone-900">1,00 <span class="font-normal text-stone-500">cái (định mức tính cho 1 sản phẩm)</span></dd>
            </div>
            <div class="flex items-center gap-4">
                <dt class="w-32 font-bold text-stone-700">Loại BOM</dt>
                <dd class="space-y-1 text-stone-700">
                    <label class="flex items-center gap-2"><span class="flex h-4 w-4 items-center justify-center rounded-full border-2 border-mint-700"><span class="h-2 w-2 rounded-full bg-mint-700"></span></span> Sản xuất sản phẩm này</label>
                </dd>
            </div>
        </dl>

        <div class="grid grid-cols-2 gap-3 text-center">
            <div class="rounded-lg bg-stone-50 p-3">
                <p class="text-xs text-stone-500">Giá vốn nguyên liệu / 1 SP</p>
                <p class="mt-1 text-xl font-extrabold text-stone-800" x-text="fmt(editing ? draftCost : {{ $unitCost }})"></p>
            </div>
            <div class="rounded-lg bg-stone-50 p-3">
                <p class="text-xs text-stone-500">Giá bán</p>
                <p class="mt-1 text-xl font-extrabold text-amber-800">{{ number_format($product->price) }} đ</p>
            </div>
            <div class="rounded-lg p-3" :class="margin === null ? 'bg-stone-50' : (margin < 30 ? 'bg-red-50' : 'bg-emerald-50')">
                <p class="text-xs text-stone-500">Biên lợi nhuận gộp</p>
                <p class="mt-1 text-xl font-extrabold" :class="margin === null ? 'text-stone-400' : (margin < 30 ? 'text-red-700' : 'text-emerald-700')" x-text="margin === null ? '—' : margin.toFixed(1) + '%'"></p>
            </div>
            <div class="rounded-lg bg-mint-700/10 p-3">
                <p class="text-xs text-stone-500">Làm tối đa với tồn kho hiện tại</p>
                @if ($capacity['max'] !== null)
                    <p class="mt-1 text-xl font-extrabold text-mint-800">{{ number_format($capacity['max']) }} cái</p>
                    <p class="text-[11px] text-stone-500">Hạn chế bởi: {{ $capacity['limiting'] }}</p>
                @else
                    <p class="mt-1 text-xl font-extrabold text-stone-400">—</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Tab --}}
    <div class="flex gap-2 border-b border-stone-200 text-sm">
        <button type="button" @click="tab = 'components'" :class="tab === 'components' ? 'border-mint-700 text-mint-800' : 'border-transparent text-stone-500 hover:text-mint-700'" class="-mb-px border-b-2 px-4 py-2.5 font-semibold">Nguyên liệu (Components)</button>
        <button type="button" @click="tab = 'calc'" :class="tab === 'calc' ? 'border-mint-700 text-mint-800' : 'border-transparent text-stone-500 hover:text-mint-700'" class="-mb-px border-b-2 px-4 py-2.5 font-semibold">Tính nguyên liệu theo số lượng</button>
    </div>

    {{-- TAB 1: Nguyên liệu --}}
    <div x-show="tab === 'components'">
        {{-- Chế độ xem --}}
        <div x-show="!editing" class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 text-left text-xs font-bold uppercase tracking-wider text-stone-500">
                    <tr><th class="px-4 py-3">Nguyên liệu</th><th class="px-4 py-3 text-right">Tồn kho</th><th class="px-4 py-3 text-right">Định lượng / 1 SP</th><th class="px-4 py-3">Đơn vị</th><th class="px-4 py-3 text-right">Giá vốn / đơn vị</th><th class="px-4 py-3 text-right">Thành tiền</th></tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <template x-for="l in lines" :key="l.ingredient_id">
                        <tr>
                            <td class="px-4 py-2.5 font-semibold text-stone-800"><i class="fa-solid fa-wheat-awn mr-2 text-mint-700/60"></i><span x-text="l.name"></span></td>
                            <td class="px-4 py-2.5 text-right" :class="l.stock < l.quantity ? 'font-bold text-red-700' : 'text-stone-600'" x-text="l.stock.toLocaleString('vi-VN')"></td>
                            <td class="px-4 py-2.5 text-right font-bold text-stone-900" x-text="num(l.quantity)"></td>
                            <td class="px-4 py-2.5 text-stone-500" x-text="l.unit"></td>
                            <td class="px-4 py-2.5 text-right text-stone-600" x-text="fmt(l.cost)"></td>
                            <td class="px-4 py-2.5 text-right font-semibold text-stone-800" x-text="fmt(Math.round(l.quantity * l.cost))"></td>
                        </tr>
                    </template>
                    <tr x-show="lines.length === 0" x-cloak>
                        <td colspan="6" class="px-4 py-10 text-center text-stone-400">
                            Sản phẩm này chưa có công thức. {{ $canEdit ? 'Bấm "Chỉnh sửa công thức" để thêm nguyên liệu.' : 'Hãy báo Quản lý khai báo công thức.' }}
                        </td>
                    </tr>
                </tbody>
                <tfoot x-show="lines.length > 0">
                    <tr class="bg-[#d8e8d2]/60 text-sm font-bold text-mint-900">
                        <td class="px-4 py-2.5" colspan="5">Tổng giá vốn nguyên liệu cho 1 sản phẩm</td>
                        <td class="px-4 py-2.5 text-right" x-text="fmt({{ $unitCost }})"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Chế độ sửa (chỉ Quản lý) --}}
        @if ($canEdit)
        <form x-show="editing" x-cloak method="POST" action="{{ route('admin.bom.update', $product) }}" class="space-y-3" @submit="if (!canSave) $event.preventDefault()">
            @csrf
            @method('PUT')
            <div class="overflow-hidden rounded-xl border border-mint-700/40 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 text-left text-xs font-bold uppercase tracking-wider text-stone-500">
                        <tr><th class="px-4 py-3">Nguyên liệu</th><th class="px-4 py-3 text-right">Tồn kho</th><th class="px-4 py-3 text-right">Định lượng / 1 SP</th><th class="px-4 py-3">Đơn vị</th><th class="px-4 py-3 text-right">Thành tiền</th><th class="w-10 px-2 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <template x-for="(d, i) in draft" :key="d.key">
                            <tr>
                                <td class="px-4 py-2">
                                    <select :name="`lines[${i}][ingredient_id]`" x-model.number="d.ingredient_id" required
                                            class="w-full min-w-[200px] rounded-lg border border-stone-300 bg-white px-2 py-1.5 text-sm focus:border-mint-700 focus:outline-none">
                                        <option value="">-- Chọn nguyên liệu --</option>
                                        <template x-for="ing in ingredients" :key="ing.id">
                                            <option :value="ing.id" :disabled="usedElsewhere(ing.id, d.key)" x-text="ing.name"></option>
                                        </template>
                                    </select>
                                </td>
                                <td class="px-4 py-2 text-right text-stone-500" x-text="ing(d.ingredient_id) ? ing(d.ingredient_id).stock.toLocaleString('vi-VN') : '—'"></td>
                                <td class="px-4 py-2 text-right">
                                    <input type="number" step="0.01" min="0.01" :name="`lines[${i}][quantity]`" x-model.number="d.quantity" required
                                           class="w-28 rounded-lg border border-stone-300 px-2 py-1.5 text-right font-bold focus:border-mint-700 focus:outline-none">
                                </td>
                                <td class="px-4 py-2 text-stone-500" x-text="ing(d.ingredient_id) ? ing(d.ingredient_id).unit : '—'"></td>
                                <td class="px-4 py-2 text-right font-semibold text-stone-800" x-text="ing(d.ingredient_id) ? fmt(Math.round((d.quantity || 0) * ing(d.ingredient_id).cost)) : '—'"></td>
                                <td class="px-2 py-2 text-center"><button type="button" @click="removeLine(d.key)" class="text-stone-400 hover:text-red-600" title="Xóa dòng"><i class="fa-solid fa-trash-can"></i></button></td>
                            </tr>
                        </template>
                        <tr>
                            <td colspan="6" class="px-4 py-2.5">
                                <button type="button" @click="addLine()" class="text-sm font-semibold text-mint-700 hover:underline"><i class="fa-solid fa-plus mr-1"></i>Thêm một dòng</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p x-show="duplicate" x-cloak class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">Mỗi nguyên liệu chỉ được xuất hiện một lần.</p>
            <div class="flex justify-end gap-2">
                <button type="button" @click="cancelEdit()" class="rounded-lg border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-600 hover:bg-stone-50">Hủy</button>
                <button type="submit" :disabled="!canSave" class="rounded-lg bg-mint-700 px-5 py-2 text-sm font-semibold text-white hover:bg-mint-800 disabled:cursor-not-allowed disabled:opacity-50"><i class="fa-solid fa-floppy-disk mr-1"></i>Lưu công thức</button>
            </div>
        </form>
        @endif
    </div>

    {{-- TAB 2: Máy tính nguyên liệu --}}
    <div x-show="tab === 'calc'" x-cloak class="space-y-3 rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
        <label class="flex flex-wrap items-center gap-3 text-sm font-semibold text-stone-700">
            Muốn làm
            <input type="number" min="1" step="1" x-model.number="batchQty" class="w-28 rounded-lg border border-stone-300 px-3 py-2 text-right text-lg font-bold focus:border-mint-700 focus:outline-none">
            sản phẩm
            <span class="text-xs font-normal text-stone-400">(dự báo/kế hoạch ngày mai có thể dùng để nhập số này)</span>
        </label>
        <div class="overflow-hidden rounded-lg border border-stone-200">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 text-left text-xs font-bold uppercase text-stone-500">
                    <tr><th class="px-3 py-2">Nguyên liệu</th><th class="px-3 py-2 text-right">Cần dùng</th><th class="px-3 py-2 text-right">Tồn kho</th><th class="px-3 py-2">Kết quả</th></tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <template x-for="l in lines" :key="l.ingredient_id">
                        <tr :class="needed(l) > l.stock ? 'bg-red-50' : ''">
                            <td class="px-3 py-1.5" x-text="l.name"></td>
                            <td class="px-3 py-1.5 text-right font-semibold" x-text="needed(l).toLocaleString('vi-VN') + ' ' + l.unit"></td>
                            <td class="px-3 py-1.5 text-right text-stone-600" x-text="l.stock.toLocaleString('vi-VN')"></td>
                            <td class="px-3 py-1.5 text-xs font-semibold" :class="needed(l) > l.stock ? 'text-red-700' : 'text-emerald-700'"
                                x-text="needed(l) > l.stock ? 'Thiếu ' + (needed(l) - l.stock).toLocaleString('vi-VN') : 'Đủ'"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>[x-cloak]{display:none !important}</style>
@endpush

@push('scripts')
<script>
    function bomPage(cfg) {
        let seq = 0;
        const blank = () => ({ key: ++seq, ingredient_id: '', quantity: 1 });
        return {
            tab: 'components', editing: false, batchQty: 100,
            lines: cfg.lines, ingredients: cfg.ingredients, draft: [],

            init() {
                // Nếu lưu lỗi (validation) thì mở lại chế độ sửa với dữ liệu người dùng vừa nhập
                if (cfg.canEdit && cfg.oldLines) {
                    this.draft = Object.values(cfg.oldLines).map(l => ({ key: ++seq, ingredient_id: Number(l.ingredient_id) || '', quantity: Number(l.quantity) || 1 }));
                    this.editing = true;
                }
            },
            fmt(n) { return new Intl.NumberFormat('vi-VN').format(n) + ' đ'; },
            num(n) { return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(n); },
            needed(l) { return Math.ceil(Math.round(l.quantity * (this.batchQty || 0) * 10000) / 10000); },

            ing(id) { return this.ingredients.find(i => i.id === Number(id)); },
            usedElsewhere(id, key) { return this.draft.some(d => d.key !== key && Number(d.ingredient_id) === id); },

            startEdit() {
                this.draft = this.lines.map(l => ({ key: ++seq, ingredient_id: l.ingredient_id, quantity: l.quantity }));
                if (this.draft.length === 0) this.draft.push(blank());
                this.editing = true; this.tab = 'components';
            },
            cancelEdit() { this.editing = false; this.draft = []; },
            addLine() { this.draft.push(blank()); },
            removeLine(key) { this.draft = this.draft.filter(d => d.key !== key); },

            get duplicate() {
                const ids = this.draft.map(d => Number(d.ingredient_id)).filter(Boolean);
                return new Set(ids).size !== ids.length;
            },
            get canSave() {
                return this.draft.length > 0 && !this.duplicate && this.draft.every(d => Number(d.ingredient_id) > 0 && Number(d.quantity) > 0);
            },
            get draftCost() {
                return this.draft.reduce((s, d) => { const i = this.ing(d.ingredient_id); return s + (i ? Math.round((Number(d.quantity) || 0) * i.cost) : 0); }, 0);
            },
            get margin() {
                const cost = this.editing ? this.draftCost : {{ $unitCost }};
                return cfg.price > 0 && cost > 0 ? (cfg.price - cost) / cfg.price * 100 : null;
            },
        };
    }
</script>
@endpush
