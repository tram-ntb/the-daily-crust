<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Trang tài khoản của CHÍNH mình (Customer, Staff, Manager). Vai trò và chức vụ không tự đổi được. */
class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('account.show', [
            'user' => $user,
            'stats' => match ($user->role) {
                'customer' => ['orders' => $user->onlineOrders()->count()],
                'manager' => [
                    'staff' => User::where('role', 'staff')->count(),
                    'customers' => User::where('role', 'customer')->count(),
                ],
                default => [],
            },
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->user_id, 'user_id')],
            'phone' => ['required', 'regex:/^0?\d{9,10}$/'],
        ], [
            'full_name.required' => 'Vui lòng nhập họ tên.',
            'email.unique' => 'Email này đã được tài khoản khác sử dụng.',
            'phone.regex' => 'Số điện thoại gồm 9–10 chữ số (ví dụ 0905123456).',
        ]);

        $user->update([
            'full_name' => trim($data['full_name']),
            'email' => $data['email'],
            'phone' => $data['phone'],
        ]);

        return redirect()->route('account.show')->with('success', 'Đã cập nhật thông tin tài khoản.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed', 'different:current_password'],
        ], [
            'password.confirmed' => 'Mật khẩu nhập lại không khớp.',
            'password.min' => 'Mật khẩu mới tối thiểu 6 ký tự.',
            'password.different' => 'Mật khẩu mới phải khác mật khẩu hiện tại.',
        ]);

        if (! Hash::check($data['current_password'], (string) $user->password)) {
            return back()->withErrors(['current_password' => 'Mật khẩu hiện tại không đúng.']);
        }

        $user->update(['password' => $data['password']]);   // cast 'hashed' tự mã hóa

        return redirect()->route('account.show')->with('success', 'Đã đổi mật khẩu.');
    }
}
