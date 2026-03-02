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

    public function handle(ZKTecoService $zkService, AttendanceProcessor $processor): void
    {
        $locations = Location::where('reloj_activo', true)->get();

        foreach ($locations as $location) {
            $zkService->syncLocation($location);
        }

        $processor->processUnprocessedLogs();
    }
}