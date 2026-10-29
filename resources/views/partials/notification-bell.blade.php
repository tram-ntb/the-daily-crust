{{-- Chuông thông báo: số chưa đọc thật + dropdown danh sách, tự cập nhật định kỳ (không cần WebSocket).
     Dữ liệu từ bảng `notifications` của CHÍNH người đăng nhập (route notifications.feed / read / readAll). --}}
@php
    try {
        $bellUnread = Auth::user()->unreadNotifications()->count();
    } catch (\Throwable $e) {
        $bellUnread = 0;   // chưa chạy migration bảng notifications: chuông vẫn hiện, không làm hỏng trang
    }
@endphp

<script>
    // Khai báo trước khi Alpine khởi động (Alpine được nạp với defer)
    function notifBell() {
        const ICON = { // class đầy đủ để Tailwind CDN nhận diện
            emerald: 'bg-emerald-100 text-emerald-700', sky: 'bg-sky-100 text-sky-700', amber: 'bg-amber-100 text-amber-700',
            red: 'bg-red-100 text-red-700', stone: 'bg-stone-100 text-stone-600',
        };
        return {
            open: false, loading: false, failed: false,
            unread: 0, items: [], cfg: {}, timer: null,
            init() {
                this.cfg = this.$root.dataset;
                this.unread = parseInt(this.cfg.unread || '0', 10);
                this.refresh();
                this.timer = setInterval(() => { if (!document.hidden) this.refresh(); }, parseInt(this.cfg.poll || '30', 10) * 1000);
                document.addEventListener('visibilitychange', () => { if (!document.hidden) this.refresh(); });
            },
            iconClass(c) { return ICON[c] || ICON.stone; },
            badge() { return this.unread > 9 ? '9+' : String(this.unread); },
            async call(url, method) {
                const res = await fetch(url, {
                    method: method || 'GET', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.cfg.token },
                });
                if (!res.ok) throw new Error(res.status);
                return res.json();
            },
            async refresh() {
                try {
                    this.loading = this.items.length === 0;
                    const d = await this.call(this.cfg.feed);
                    this.unread = d.unread; this.items = d.items; this.failed = false;
                } catch (e) { this.failed = true; } finally { this.loading = false; }
            },
            toggle() { this.open = !this.open; if (this.open) this.refresh(); },
            async openItem(n) {                       // bấm một thông báo: đánh dấu đã đọc rồi tới trang liên quan
                try { if (!n.read) await this.call(this.cfg.read.replace('__ID__', n.id), 'POST'); } catch (e) {}
                window.location.href = n.url;
            },
            async markRead(n) {
                try {
                    const d = await this.call(this.cfg.read.replace('__ID__', n.id), 'POST');
                    n.read = true; this.unread = d.unread;
                } catch (e) {}
            },
            async markAll() {
                try {
                    await this.call(this.cfg.readall, 'POST');
                    this.items.forEach(n => n.read = true); this.unread = 0;
                } catch (e) {}
            },
        };
    }
</script>

<div class="relative"
     x-data="notifBell()"
     @click.outside="open = false" @keydown.escape.window="open = false"
     data-unread="{{ $bellUnread }}"
     data-poll="{{ (int) config('crust.bell.poll_seconds', 30) }}"
     data-token="{{ csrf_token() }}"
     data-feed="{{ route('notifications.feed') }}"
     data-read="{{ route('notifications.read', ['id' => '__ID__']) }}"
     data-readall="{{ route('notifications.readAll') }}">

    <button type="button" @click="toggle()" class="relative text-stone-600 hover:text-stone-900 focus:outline-none" aria-label="Thông báo" aria-haspopup="true" :aria-expanded="open">
        <i class="fa-regular fa-bell text-xl"></i>
        {{-- Số render sẵn từ server để không bị nhấp nháy; Alpine cập nhật sau đó --}}
        <span x-show="unread > 0" @if ($bellUnread < 1) x-cloak @endif
              class="absolute -top-1.5 -right-2 min-w-[1rem] h-4 px-1 bg-red-500 text-white text-[10px] rounded-full flex items-center justify-center font-bold"
              x-text="badge()">{{ $bellUnread > 9 ? '9+' : $bellUnread }}</span>
    </button>

    <div x-show="open" x-cloak x-transition
         class="absolute right-0 mt-3 w-[22rem] max-w-[calc(100vw-2rem)] bg-white border border-stone-200 rounded-xl shadow-xl z-50 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-stone-100">
            <p class="font-bold text-stone-900">Thông báo <span x-show="unread > 0" x-cloak class="ml-1 text-xs font-semibold text-red-600" x-text="'(' + unread + ' mới)'"></span></p>
            <button type="button" @click="markAll()" x-show="unread > 0" x-cloak class="text-xs font-semibold text-mint-700 hover:underline">Đánh dấu tất cả đã đọc</button>
        </div>

        <div class="max-h-96 overflow-y-auto divide-y divide-stone-100">
            <p x-show="loading" class="px-4 py-6 text-center text-sm text-stone-400">Đang tải...</p>
            <p x-show="failed && items.length === 0" x-cloak class="px-4 py-6 text-center text-sm text-red-600">Không tải được thông báo.</p>
            <p x-show="!loading && !failed && items.length === 0" x-cloak class="px-4 py-8 text-center text-sm text-stone-400">
                <i class="fa-regular fa-bell-slash mb-2 block text-2xl"></i>Chưa có thông báo nào.
            </p>

            <template x-for="n in items" :key="n.id">
                <div class="flex items-start gap-3 px-4 py-3 cursor-pointer hover:bg-stone-50" :class="n.read ? '' : 'bg-[#eef4ec]/60'" @click="openItem(n)">
                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full" :class="iconClass(n.color)">
                        <i class="fa-solid" :class="n.icon"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm leading-snug text-stone-900" :class="n.read ? 'font-medium' : 'font-bold'" x-text="n.title"></p>
                        <p class="mt-0.5 text-xs leading-snug text-stone-600" x-text="n.body"></p>
                        <p class="mt-1 text-[11px] text-stone-400" x-text="n.time"></p>
                    </div>
                    <button type="button" x-show="!n.read" @click.stop="markRead(n)" title="Đánh dấu đã đọc"
                            class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-mint-600 hover:ring-4 hover:ring-mint-600/20"></button>
                </div>
            </template>
        </div>

        <a href="{{ route('notifications.index') }}" class="block border-t border-stone-100 bg-stone-50 px-4 py-2.5 text-center text-sm font-semibold text-mint-700 hover:bg-stone-100">
            Xem tất cả thông báo
        </a>
    </div>
</div>
