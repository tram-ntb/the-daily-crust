<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Sinh đơn hàng MÔ PHỎNG (orders.note = 'SIM-DATA') cho giai đoạn dữ liệu bị thưa, bằng python/seed_simulated_orders.py.
 * Chỉ dùng cho môi trường phát triển / đồ án. Đơn mô phỏng không gắn lot_id nên không trừ kho.
 *
 *   php artisan app:seed-simulated-orders --dry-run          (xem kế hoạch, chưa ghi)
 *   php artisan app:seed-simulated-orders                    (ghi thật, hỏi xác nhận)
 *   php artisan app:seed-simulated-orders --from=2026-10-24 --to=2026-11-30 --append   (sinh nối tiếp, không trùng)
 *   php artisan app:seed-simulated-orders --replace          (xóa đơn mô phỏng cũ rồi sinh lại)
 *   php artisan app:seed-simulated-orders --purge            (chỉ xóa đơn mô phỏng)
 */
class SeedSimulatedOrders extends Command
{
    protected $signature = 'app:seed-simulated-orders
        {--from=2026-03-01 : Ngày bắt đầu (YYYY-MM-DD)}
        {--to=2026-10-23 : Ngày kết thúc (YYYY-MM-DD)}
        {--per-day=200 : Số đơn trung bình mỗi ngày}
        {--noise=0.12 : Độ lệch nhiễu ngẫu nhiên của số đơn mỗi ngày}
        {--seed=2026 : Hạt giống ngẫu nhiên (cùng seed cho cùng kết quả)}
        {--append : Sinh nối tiếp, giữ đơn mô phỏng cũ, chỉ thêm những ngày chưa có}
        {--replace : Xóa đơn mô phỏng cũ rồi sinh lại}
        {--purge : CHỈ xóa đơn mô phỏng, không sinh mới}
        {--force : Không hỏi xác nhận}
        {--dry-run : Chỉ in kế hoạch, không ghi database}';

    protected $description = 'Sinh (hoặc xóa) đơn hàng mô phỏng gắn cờ SIM-DATA để bổ sung dữ liệu huấn luyện dự báo';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Lệnh này tạo dữ liệu giả nên bị chặn ở môi trường production.');

            return self::FAILURE;
        }

        $script = dirname((string) config('forecast.script')).DIRECTORY_SEPARATOR.'seed_simulated_orders.py';
        if (! is_file($script)) {
            $this->error("Không tìm thấy script Python: {$script}");

            return self::FAILURE;
        }

        $cmd = [(string) config('forecast.python'), $script, '--env-file', base_path('.env'),
            '--from', (string) $this->option('from'), '--to', (string) $this->option('to'),
            '--per-day', (string) $this->option('per-day'), '--noise', (string) $this->option('noise'),
            '--seed', (string) $this->option('seed')];

        foreach (['append', 'replace', 'purge', 'dry-run'] as $flag) {
            if ($this->option($flag)) {
                $cmd[] = '--'.$flag;
            }
        }

        $writes = ! $this->option('dry-run');
        if ($writes && ! $this->option('force')) {
            $what = $this->option('purge') ? 'XÓA toàn bộ đơn mô phỏng (note = SIM-DATA)' : 'GHI đơn mô phỏng vào orders / order_details';
            if (! $this->confirm("Sắp {$what}. Đã sao lưu database chưa? Tiếp tục?", false)) {
                $this->warn('Đã hủy, chưa thay đổi gì.');

                return self::SUCCESS;
            }
        }

        $process = new Process($cmd, base_path(), ['PYTHONIOENCODING' => 'utf-8']);
        $process->setTimeout(900);

        try {
            $process->run(fn (string $type, string $buffer) => $this->output->write($buffer, false, OutputInterface::OUTPUT_RAW));
        } catch (\Throwable $e) {
            $this->error('Không chạy được Python: '.$e->getMessage());
            $this->line('Kiểm tra FORECAST_PYTHON trong .env và đã cài: pip install pandas numpy scikit-learn pymysql');

            return self::FAILURE;
        }

        if (! $process->isSuccessful()) {
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
