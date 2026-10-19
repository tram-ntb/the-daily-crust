<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:run-production-forecast')]
#[Description('Command description')]
class RunProductionForecast extends Command
{
    protected $signature = 'forecast:run
        {--as-of= : Giả lập ngày chạy (YYYY-MM-DD), mặc định hôm nay}
        {--model=auto : auto | hgb}
        {--dry-run : Chỉ in kết quả, không ghi database}';

    protected $description = 'Chạy script Python dự báo sản lượng ngày mai và ghi vào production_forecasts';

    public function handle(): int
    {
        $script = config('forecast.script');
        if (! is_file($script)) {
            $this->error("Không tìm thấy script: {$script}");

            return self::FAILURE;
        }

        $cmd = [config('forecast.python'), $script, '--env-file', base_path('.env'), '--model', $this->option('model')];
        if ($this->option('as-of')) {
            array_push($cmd, '--as-of', $this->option('as-of'));
        }
        if ($this->option('dry-run')) {
            $cmd[] = '--dry-run';
        }

        $result = Process::path(base_path())
            ->env(['PYTHONIOENCODING' => 'utf-8'])
            ->timeout(config('forecast.timeout'))
            ->run($cmd);

        $this->line($result->output());
        if ($result->failed()) {
            $this->error($result->errorOutput() ?: 'Script Python thất bại.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
