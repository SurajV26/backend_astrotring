<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DeleteScheduledFiles extends Command
{
    protected $signature = 'app:delete-scheduled-files';
    protected $description = 'Delete files once their scheduled date arrives';

    public function handle()
    {
        $configPath = base_path('delete_schedule.json');

        if (!file_exists($configPath)) {
            $this->info('No schedule file found.');
            return;
        }

        $config = json_decode(file_get_contents($configPath), true);

        if (!$config || now()->lt($config['delete_at'])) {
            $this->info('Not time yet.');
            return;
        }

        foreach ($config['files'] as $file) {
            $path = base_path($file);
            if (file_exists($path)) {
                unlink($path);
                $this->info("Deleted: $file");
            } else {
                $this->info("Not found: $file");
            }
        }

        unlink($configPath);
        $this->info('Schedule file removed.');
    }
}