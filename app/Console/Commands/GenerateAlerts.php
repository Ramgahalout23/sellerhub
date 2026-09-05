<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateAlerts extends Command
{
    protected $signature = 'selling-hub:generate-alerts';

    protected $description = 'Run all alert checks (return reminders + low stock)';

    public function handle(): int
    {
        $this->info('📋 Running all alert checks...');
        $this->newLine();

        $this->line('━━━ Return Reminders ━━━');
        $this->call(CheckReturnReminders::class);

        $this->newLine();
        $this->line('━━━ Low Stock Check ━━━');
        $this->call(CheckLowStock::class);

        $this->newLine();
        $this->info('✅ All alert checks completed.');

        return self::SUCCESS;
    }
}
