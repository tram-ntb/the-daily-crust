<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        ];
    }
}
