<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'users';         // Tên bảng trong MySQL

    protected $primaryKey = 'user_id';   // Khóa chính của bảng

    // Tắt tính năng tự động quản lý created_at và updated_at vì bảng không có cột này
    public $timestamps = false;

    protected $fillable = [
        'username',
        'password',
        'full_name',
        'role',
        'position',
        'phone',
        'email',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed', // Tự động mã hóa mật khẩu khi gán giá trị
            'is_active' => 'boolean',
        ];
    }

    // ===== Vai trò =====

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer' || $this->role === null;
    }

    /** Manager hoặc Staff: tài khoản nội bộ, không đặt hàng online. */
    public function isInternal(): bool
    {
        return in_array($this->role, ['manager', 'staff'], true);
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'manager' => 'Quản lý',
            'staff' => 'Nhân viên',
            default => 'Khách hàng',
        };
    }

    /** Tài khoản bị khóa khi cột is_active = 0 (chưa chạy migration hoặc NULL được coi là đang hoạt động). */
    public function isLocked(): bool
    {
        return $this->is_active === false;
    }

    /** Số điện thoại hiển thị: cột phone có thể là kiểu số nên mất số 0 đầu, bù lại khi hiển thị. */
    public function phoneText(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->phone);
        if ($digits === '') {
            return null;
        }

        return $digits[0] === '0' ? $digits : '0'.$digits;
    }

    /** Đơn hàng ONLINE do khách này đặt. */
    public function onlineOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'created_by', 'user_id')->where('order_channel', Order::CHANNEL_APP);
    }
}
