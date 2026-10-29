<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/** Thông báo trong hệ thống (bảng `notifications`) của CHÍNH người đăng nhập, mọi vai trò đều dùng được. */
class NotificationController extends Controller
{
    /** Trang "Lịch sử thông báo". */
    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = $request->input('filter') === 'unread' ? 'unread' : 'all';

        $items = ($filter === 'unread' ? $user->unreadNotifications() : $user->notifications())
            ->paginate(15)->withQueryString()
            ->through(fn (DatabaseNotification $n) => self::present($n));

        return view('notifications.index', [
            'items' => $items,
            'filter' => $filter,
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    /** Dữ liệu cho chuông: số chưa đọc + vài thông báo mới nhất. Trình duyệt gọi định kỳ (xem partials/notification-bell). */
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = max(1, (int) config('crust.bell.limit', 10));

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->limit($limit)->get()->map(fn (DatabaseNotification $n) => self::present($n))->values(),
        ])->header('Cache-Control', 'no-store');
    }

    /** Đánh dấu một thông báo đã đọc (chỉ thông báo của chính mình). */
    public function read(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $n = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $n->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'unread' => $request->user()->unreadNotifications()->count()]);
        }

        // Từ trang lịch sử: bấm "Xem" thì đi tới trang liên quan, bấm "Đã đọc" thì ở lại
        return $request->boolean('go') ? redirect(self::present($n)['url']) : back();
    }

    public function readAll(Request $request): JsonResponse|RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'unread' => 0])
            : back()->with('success', 'Đã đánh dấu tất cả thông báo là đã đọc.');
    }

    /** @return array{id:string,kind:string,title:string,body:string,icon:string,color:string,url:string,read:bool,time:string,at:string} */
    private static function present(DatabaseNotification $n): array
    {
        $d = (array) $n->data;
        $url = (string) ($d['url'] ?? '');
        if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {   // chỉ cho phép đường dẫn nội bộ
            $url = '/';
        }

        return [
            'id' => $n->id,
            'kind' => (string) ($d['kind'] ?? 'system'),
            'title' => (string) ($d['title'] ?? 'Thông báo'),
            'body' => (string) ($d['body'] ?? ''),
            'icon' => (string) ($d['icon'] ?? 'fa-bell'),
            'color' => (string) ($d['color'] ?? 'stone'),
            'url' => $url,
            'read' => $n->read_at !== null,
            'time' => $n->created_at?->locale('vi')->diffForHumans() ?? '',
            'at' => $n->created_at?->format('H:i d/m/Y') ?? '',
        ];
    }
}
