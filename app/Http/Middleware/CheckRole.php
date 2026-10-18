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

        // Kiểm tra xem user hiện tại có thuộc một trong các role được phép hay không
        // Giả sử cột lưu phân quyền trong bảng users là 'role'
        if (in_array($user->role, $roles)) {
            return $next($request);
        }

        abort(403, 'Bạn không có quyền truy cập khu vực này.');
    }
}
