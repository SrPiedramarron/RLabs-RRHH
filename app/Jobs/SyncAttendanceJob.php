<?php
namespace App\Jobs;
use App\Models\Location;
use App\Services\ZKTecoService;
use App\Services\ZKTecoSDKService;
use App\Services\AttendanceProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
class SyncAttendanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $timeout = 600;
    public int $tries = 3;
    public int $backoff = 60;
    public function handle(ZKTecoService $zkService, ZKTecoSDKService $zkSDKService, AttendanceProcessor $processor): void
    {
        ini_set('memory_limit', '512M');
        $locations = Location::where('reloj_activo', true)->get();
        foreach ($locations as $location) {
            if ($location->reloj_tipo === 'zksdk') {
                $zkSDKService->syncLocation($location);
            } elseif ($location->reloj_tipo === 'zkadms') {
                // ADMS es push — el reloj envía datos al servidor, no hay nada que pullear
                continue;
            } else {
                $zkService->syncLocation($location);
            }
        }
        $processor->processUnprocessedLogs();
    }
}
