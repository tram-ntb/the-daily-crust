<?php

namespace App\Support;

/**
 * Chuẩn bị cách gọi Python từ web/artisan để chạy GIỐNG HỆT khi gõ tay trong terminal.
 *
 * Vì sao cần: process do PHP tạo ra (nhất là khi PHP chạy dưới `php artisan serve`, Apache/XAMPP, IIS) có thể thiếu
 * các biến môi trường Windows mà Python/numpy/scipy/scikit-learn cần (USERPROFILE, APPDATA, LOCALAPPDATA, TEMP, PATH có
 * thư mục Python...). Terminal của VS Code thì có đủ nên chạy tay luôn thành công.
 *
 * Lớp này (1) tìm đường dẫn tuyệt đối của python.exe, (2) dựng lại các biến còn thiếu, (3) gỡ PYTHONHOME/PYTHONPATH lạ.
 */
class PythonEnvironment
{
    /** Đường dẫn Python sẽ dùng: ưu tiên FORECAST_PYTHON tuyệt đối; tên trần (python, py) được tìm trong PATH. */
    public static function executable(string $configured, ?string $path = null, ?bool $windows = null): string
    {
        $windows ??= DIRECTORY_SEPARATOR === '\\';
        $configured = trim($configured, " \t\"'");

        if (strtolower($configured) === 'py') {
            return $configured;                                    // launcher `py` của Windows: giữ nguyên
        }
        if (preg_match('#[\\\\/]#', $configured)) {
            if (is_file($configured)) {
                return $configured;                                // đường dẫn đầy đủ và đúng: dùng luôn
            }
            $configured = 'python';                                // đường dẫn trong .env SAI (gõ nhầm tên thư mục...): tìm python trong PATH
        }
        if ($configured === '') {
            $configured = 'python';
        }

        $path ??= (string) getenv('PATH');
        foreach (explode($windows ? ';' : ':', $path) as $dir) {
            if ($dir === '' || stripos($dir, 'WindowsApps') !== false) {   // bỏ "python giả" của Microsoft Store
                continue;
            }
            $file = rtrim($dir, '\\/').($windows ? '\\' : '/').$configured.($windows ? '.exe' : '');
            if (is_file($file)) {
                return $file;
            }
        }

        return $configured;
    }

    /**
     * Biến môi trường truyền cho Process: TOÀN BỘ môi trường hiện có của PHP (truyền tường minh) + phần dựng lại còn thiếu.
     *
     * Truyền tường minh là bắt buộc: dưới `php artisan serve` (cli-server) PHP vẫn đọc được SystemRoot/USERPROFILE/TEMP bằng
     * getenv() nhưng Symfony Process không chuyển tiếp chúng cho process con, khiến Python thiếu SystemRoot
     * và `import asyncio` / scikit-learn lỗi "WinError 10106".
     *
     * @param  array<string,string|false>|null  $current  môi trường hiện có của PHP (mặc định getenv())
     * @return array<string,string|false>  khóa => giá trị (false = gỡ biến)
     */
    public static function environment(string $python, ?array $current = null, ?bool $windows = null): array
    {
        $windows ??= DIRECTORY_SEPARATOR === '\\';
        $current ??= (array) getenv();

        $env = [];
        foreach ($current as $k => $v) {
            if (is_string($v) && $v !== '') {
                $env[(string) $k] = $v;
            }
        }

        self::put($env, 'PYTHONIOENCODING', 'utf-8');
        self::put($env, 'PYTHONUTF8', '1');
        self::put($env, 'PYTHONUNBUFFERED', '1');
        self::put($env, 'PYTHONHOME', false);   // PYTHONHOME/PYTHONPATH do phần mềm khác đặt sẽ làm Python nạp nhầm thư viện
        self::put($env, 'PYTHONPATH', false);

        if (! $windows) {
            return $env;
        }

        $sys = self::get($env, 'SystemRoot') ?? self::get($env, 'windir') ?? 'C:\\Windows';
        $fill = [
            'SystemRoot' => $sys, 'windir' => $sys,
            'COMSPEC' => $sys.'\\System32\\cmd.exe',
            'PATHEXT' => '.COM;.EXE;.BAT;.CMD',
            'NUMBER_OF_PROCESSORS' => '4',
            'PROCESSOR_ARCHITECTURE' => 'AMD64',
        ];

        // Hồ sơ người dùng: lấy từ biến sẵn có, nếu thiếu thì suy ra từ đường dẫn python.exe (C:\Users\<tên>\AppData\...)
        $profile = self::get($env, 'USERPROFILE') ?? self::profileFromPython($python);
        if ($profile !== null) {
            $fill += [
                'USERPROFILE' => $profile,
                'HOMEDRIVE' => substr($profile, 0, 2),
                'HOMEPATH' => substr($profile, 2),
                'APPDATA' => $profile.'\\AppData\\Roaming',
                'LOCALAPPDATA' => $profile.'\\AppData\\Local',
                'TEMP' => $profile.'\\AppData\\Local\\Temp',
                'TMP' => $profile.'\\AppData\\Local\\Temp',
            ];
        } else {
            $fill += ['TEMP' => $sys.'\\Temp', 'TMP' => $sys.'\\Temp'];
        }
        foreach ($fill as $k => $v) {
            if (self::get($env, $k) === null) {
                self::put($env, $k, $v);
            }
        }

        // PATH: thư mục chứa python.exe (cần cho DLL đi kèm) + Scripts + System32 đứng trước
        $path = self::get($env, 'PATH') ?? '';
        $front = [];
        if (preg_match('#[\\\\/]#', $python)) {
            $dir = preg_replace('#\\\\[^\\\\]*$#', '', str_replace('/', '\\', $python));   // bỏ tên file, giữ thư mục
            array_push($front, $dir, $dir.'\\Scripts');
        }
        array_push($front, $sys.'\\System32', $sys);
        self::put($env, 'PATH', implode(';', array_unique(array_filter(array_merge($front, $path === '' ? [] : explode(';', $path))))));

        return $env;
    }

    /** Đặt biến (không phân biệt hoa thường như Windows): xóa mọi khóa trùng tên rồi đặt lại, tránh "Path" và "PATH" cùng tồn tại. */
    private static function put(array &$env, string $key, string|false $value): void
    {
        foreach (array_keys($env) as $k) {
            if (strcasecmp((string) $k, $key) === 0) {
                unset($env[$k]);
            }
        }
        $env[$key] = $value;
    }

    /** "C:\Users\ThanhHV-E480\AppData\Local\Programs\Python\Python313\python.exe" => "C:\Users\ThanhHV-E480". */
    public static function profileFromPython(string $python): ?string
    {
        $p = str_replace('/', '\\', $python);

        return preg_match('#^([A-Za-z]:\\\\Users\\\\[^\\\\]+)\\\\AppData\\\\#i', $p, $m) ? $m[1] : null;
    }

    /** Lấy biến không phân biệt hoa thường (Windows); rỗng hoặc false coi như không có. */
    private static function get(array $env, string $key): ?string
    {
        foreach ($env as $k => $v) {
            if (strcasecmp((string) $k, $key) === 0 && is_string($v) && $v !== '') {
                return $v;
            }
        }

        return null;
    }
}
