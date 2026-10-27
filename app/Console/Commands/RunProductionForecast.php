<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Chạy script Python dự báo sản lượng ngày mai và ghi vào bảng production_forecasts.
 *
 * Tên lệnh DUY NHẤT của hệ thống: app:run-production-forecast
 * (routes/console.php lập lịch 21:00 bằng chính class này nên tên lệnh không thể lệch nhau).
 */
class RunProductionForecast extends Command
{
    protected $signature = 'app:run-production-forecast
        {--as-of= : Giả lập ngày chạy (YYYY-MM-DD), mặc định hôm nay}
        {--model=auto : auto | hgb}
        {--buffer= : Hệ số dự phòng cho hàng sản xuất, ví dụ 0.1 = cộng thêm 10%}
        {--commercial-categories= : Nhóm hàng nhập về, cách nhau bằng dấu | (mặc định lấy từ config/forecast.php)}
        {--bom-decides-categories= : Nhóm hàng do BOM quyết định (có BOM = pha chế tại quán), mặc định lấy từ config/forecast.php}
        {--horizon= : Số ngày nhu cầu dự báo cho hàng nhập về (mặc định 7)}
        {--feature-set=calendar : calendar (thêm lịch + ngày lễ) | basic (bộ đặc trưng cũ, để so sánh)}
        {--dry-run : Chỉ in kết quả, không ghi database}';

    protected $description = 'Chạy mô hình Python: dự báo sản lượng ngày mai (hàng sản xuất) và nhu cầu 7 ngày (hàng nhập về)';

    public function handle(): int
    {
        $script = (string) config('forecast.script');
        if (! is_file($script)) {
            $this->error("Không tìm thấy script Python: {$script}");
            $this->line('Kiểm tra file python/forecast_production.py hoặc đặt FORECAST_SCRIPT trong .env');

            return self::FAILURE;
        }

        $cmd = [(string) config('forecast.python'), $script, '--env-file', base_path('.env'), '--model', (string) $this->option('model'),
            '--feature-set', (string) $this->option('feature-set')];

        foreach (['as-of', 'buffer', 'commercial-categories', 'bom-decides-categories', 'horizon'] as $name) {
            if ($this->option($name) !== null && $this->option($name) !== '') {
                array_push($cmd, '--'.$name, (string) $this->option($name));
            }
        }
        // Nhóm hàng nhập về lấy từ config/forecast.php (nguồn duy nhất, giao diện cũng đọc từ đó)
        if (! $this->option('commercial-categories')) {
            array_push($cmd, '--commercial-categories', implode('|', (array) config('forecast.commercial_categories')));
        }
        if (! $this->option('bom-decides-categories')) {
            array_push($cmd, '--bom-decides-categories', implode('|', (array) config('forecast.bom_decides_categories')));
        }
        if ($this->option('dry-run')) {
            $cmd[] = '--dry-run';
        }

        $process = new Process($cmd, base_path(), ['PYTHONIOENCODING' => 'utf-8']);
        $process->setTimeout((float) config('forecast.timeout'));

        try {
            // Hiện kết quả của Python ngay khi nó in ra; RAW để các ký tự < > trong log không bị coi là thẻ định dạng
            $process->run(fn (string $type, string $buffer) => $this->output->write($buffer, false, OutputInterface::OUTPUT_RAW));
        } catch (ProcessTimedOutException) {
            $this->error('Script Python chạy quá '.config('forecast.timeout').' giây và đã bị dừng.');

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Không chạy được Python: '.$e->getMessage());
            $this->line('Kiểm tra FORECAST_PYTHON trong .env (ví dụ "python" hoặc "py") và đã cài: pip install pandas numpy scikit-learn pymysql');

            return self::FAILURE;
        }

        if (! $process->isSuccessful()) {
            $this->newLine();
            $this->error('Script Python thất bại (mã thoát '.$process->getExitCode().').');
            $err = trim($process->getErrorOutput());
            if ($err !== '') {
                $this->line($err);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
