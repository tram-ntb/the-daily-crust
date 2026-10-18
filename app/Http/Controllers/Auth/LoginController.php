<?php

namespace App\Http\Controllers\Auth;

use App\Models\User; // Import Model User vào Controller
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $login = trim($credentials['login']);
        $attempt = [
            'username' => $login,
            'password' => $credentials['password'],
        ];

        if (Auth::attempt($attempt, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();

            // Phân quyền điều hướng sau khi đăng nhập
            if ($user->role === 'manager' || $user->role === 'staff') {
                return redirect()->intended(route('admin.dashboard'));
            }

            return redirect()->intended(route('home'));
        }

        throw ValidationException::withMessages([
            'login' => 'Thông tin đăng nhập không chính xác.',
        ]);
    }

    // Xử lý đăng ký tài khoản khách hàng mới sử dụng Eloquent Model
    public function storeRegister(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'fullname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        // Sử dụng User Model thay vì DB::table()
        // Nhờ có casts 'password' => 'hashed' trong Model, bạn chỉ cần truyền mật khẩu thô (vd: '123456')
        // và Laravel sẽ tự động mã hóa an toàn trước khi lưu vào cơ sở dữ liệu.
        User::create([
            'username' => $validated['username'],
            'full_name' => $validated['fullname'], // Khớp với cột full_name trong DB
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => $validated['password'],  // Tự động mã hóa ngầm qua Model
            'role' => 'customer',                  // Phân quyền mặc định là khách hàng
            'position' => 'Khách hàng thành viên', // Chức vụ mặc định
        ]);

        return redirect()->route('login')->with('success', 'Đăng ký thành công! Vui lòng đăng nhập bằng tài khoản vừa tạo.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
