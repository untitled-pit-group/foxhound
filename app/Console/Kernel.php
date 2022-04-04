<?php declare(strict_types=1);
namespace App\Console;
use App\Jobs;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\GenerateSecret::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        $schedule->job(new Jobs\CleanUpStaleUploads())->everyFiveMinutes();
    }
}
