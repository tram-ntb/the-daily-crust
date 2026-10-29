<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Quản lý tài khoản NHÂN VIÊN (chỉ Manager, chặn ở route). Manager xem được danh sách Manager + Staff,
 * nhưng chỉ tạo / sửa / khóa tài khoản Staff. Không xóa tài khoản vì đã gắn với đơn hàng, mẻ nướng, phiếu kho...
 */
class StaffAccountController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->input('q', ''));

        $users = User::query()
            ->whereIn('role', ['manager', 'staff'])
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('full_name', 'like', "%{$q}%")->orWhere('username', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")->orWhere('position', 'like', "%{$q}%")))
            ->orderByRaw("role = 'manager' desc")->orderBy('full_name')
            ->paginate(20)->withQueryString();

        return view('admin.users.staff-index', ['users' => $users, 'q' => $q]);
    }

    public function create(): View
    {
        return view('admin.users.staff-form', ['staff' => null, 'positions' => config('crust.staff_positions')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'alpha_dash', 'max:50', 'unique:users,username'],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'regex:/^0?\d{9,10}$/'],
            'position' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:6'],
        ], $this->messages());

        User::create([
            'username' => $data['username'],
            'full_name' => trim($data['full_name']),
            'email' => $data['email'],
            'phone' => $data['phone'],
            'position' => $data['position'],
            'password' => $data['password'],
            'role' => 'staff',            // Manager chỉ tạo tài khoản Staff
            'is_active' => true,
        ]);

        return redirect()->route('admin.staff.index')->with('success', "Đã tạo tài khoản nhân viên {$data['full_name']}.");
    }

    public function edit(User $user): View
    {
        $this->ensureStaff($user);

        return view('admin.users.staff-form', ['staff' => $user, 'positions' => config('crust.staff_positions')]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureStaff($user);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->user_id, 'user_id')],
            'phone' => ['required', 'regex:/^0?\d{9,10}$/'],
            'position' => ['required', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'min:6'],   // để trống = giữ mật khẩu cũ
        ], $this->messages());

        $user->fill([
            'full_name' => trim($data['full_name']),
            'email' => $data['email'],
            'phone' => $data['phone'],
            'position' => $data['position'],
        ]);
        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }
        $user->save();

        return redirect()->route('admin.staff.index')->with('success', "Đã cập nhật tài khoản {$user->full_name}.");
    }

    /** Khóa / mở khóa tài khoản nhân viên. */
    public function toggle(User $user): RedirectResponse
    {
        $this->ensureStaff($user);

        $user->update(['is_active' => $user->isLocked()]);

        return back()->with('success', $user->isLocked()
            ? "Đã khóa tài khoản {$user->full_name}. Nhân viên này không đăng nhập được nữa."
            : "Đã mở khóa tài khoản {$user->full_name}.");
    }

    /** Chỉ được thao tác trên tài khoản Staff (không sửa / khóa Manager, kể cả chính mình). */
    private function ensureStaff(User $user): void
    {
        abort_unless($user->role === 'staff', 403, 'Chỉ quản lý được tài khoản nhân viên (Staff).');
    }

    private function messages(): array
    {
        return [
            'username.unique' => 'Tên đăng nhập đã tồn tại.',
            'username.alpha_dash' => 'Tên đăng nhập chỉ gồm chữ, số, dấu gạch.',
            'email.unique' => 'Email này đã được tài khoản khác sử dụng.',
            'phone.regex' => 'Số điện thoại gồm 9–10 chữ số (ví dụ 0905123456).',
            'password.min' => 'Mật khẩu tối thiểu 6 ký tự.',
        ];
    }
}
