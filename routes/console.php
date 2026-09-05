<?php

use App\Console\Commands\CheckLowStock;
use App\Console\Commands\CheckReturnReminders;
use App\Console\Commands\GenerateAlerts;
use Illuminate\Support\Facades\Schedule;

// --- Daily: Generate all alerts (return reminders + low stock) ---
Schedule::command(GenerateAlerts::class)
    ->daily()
    ->at('09:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/alerts.log'));

// --- Every 6 hours: Return reminder check (more frequent during day) ---
Schedule::command(CheckReturnReminders::class)
    ->cron('0 */6 * * *') // Every 6 hours
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/alerts.log'));

// --- Every 4 hours: Low stock check ---
Schedule::command(CheckLowStock::class)
    ->cron('0 */4 * * *') // Every 4 hours
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/alerts.log'));

// --- Weekly: Verify stock consistency with returns ---
Schedule::command('selling-hub:sync-stock-returns')
    ->weekly()
    ->mondays()
    ->at('08:00')
    ->withoutOverlapping();
