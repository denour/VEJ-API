<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Generate a daily blog post at 9 AM
        $schedule->command('posts:generate-daily')
            ->dailyAt('09:00')
            ->timezone('America/Mexico_City');

        // Poll pending image generation requests every 30 seconds
        $schedule->command('images:poll-pending')
            ->everyThirtySeconds()
            ->withoutOverlapping();

        // Process queued jobs inline (no dedicated worker instance)
        $schedule->command('queue:work --stop-when-empty --max-time=50')
            ->everyMinute()
            ->withoutOverlapping();
    })
    ->create();
