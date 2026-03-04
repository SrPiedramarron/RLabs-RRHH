<?php
namespace App\Jobs;

use App\Models\Location;
use App\Services\ZKTecoService;
use App\Services\AttendanceProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncAttendanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;      // 10 minutos
    public int $tries = 3;
    public int $backoff = 60;

    public function handle(ZKTecoService $zkService, AttendanceProcessor $processor): void
    {
        ini_set('memory_limit', '512M');

        $locations = Location::where('reloj_activo', true)->get();

        foreach ($locations as $location) {
            $zkService->syncLocation($location);
        }

        $processor->processUnprocessedLogs();
    }
}
