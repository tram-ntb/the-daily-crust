<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  string|string[]  ...$roles  (customer, staff, manager)
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Tài khoản bị Quản lý khóa trong lúc đang đăng nhập: đăng xuất ngay
        if ($user->isLocked()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['login' => 'Tài khoản đã bị khóa. Vui lòng liên hệ quản lý cửa hàng.']);
        }

        // Kiểm tra xem user hiện tại có thuộc một trong các role được phép hay không
        // Giả sử cột lưu phân quyền trong bảng users là 'role'
        if (in_array($user->role, $roles)) {
            return $next($request);
        }

        abort(403, 'Bạn không có quyền truy cập khu vực này.');
    }
}
