<?php

namespace App\Services;

use App\Models\Location;
use App\Models\AttendanceLog;
use App\Models\SyncLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZKTecoService
{
    private string $baseUrl;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.zkbio.url'), '/');
    }

    private function getToken(): string
    {
        $response = Http::post("{$this->baseUrl}/jwt-api-token-auth/", [
            'username' => config('services.zkbio.username'),
            'password' => config('services.zkbio.password'),
        ]);

        if (!$response->successful()) {
            throw new \Exception("No se pudo autenticar con ZKBio: " . $response->body());
        }

        return $response->json('token');
    }

    public function syncLocation(Location $location): SyncLog
    {
        $syncLog = SyncLog::create([
            'location_id' => $location->id,
            'iniciado_en' => now(),
            'estado'      => 'ejecutando',
        ]);

        try {
            $this->token = $this->getToken();

            $nuevos = 0;
            $pagina = 1;
            $totalLeidos = 0;

            do {
                $response = Http::withHeaders([
                    'Authorization' => "JWT {$this->token}",
                    'Content-Type'  => 'application/json',
                ])->get("{$this->baseUrl}/iclock/api/transactions/", [
                    'page'      => $pagina,
                    'page_size' => 100,
                ]);

                if (!$response->successful()) {
                    throw new \Exception("Error al obtener transacciones: " . $response->body());
                }

                $data      = $response->json();
                $registros = $data['data'] ?? [];
                $totalLeidos += count($registros);

                foreach ($registros as $record) {
                    $existe = AttendanceLog::where('location_id', $location->id)
                        ->where('reloj_id', $record['emp_code'])
                        ->where('timestamp', $record['punch_time'])
                        ->exists();

                    if (!$existe) {
                        AttendanceLog::create([
                            'location_id' => $location->id,
                            'reloj_uid'   => $record['id'],
                            'reloj_id'    => $record['emp_code'],
                            'timestamp'   => $record['punch_time'],
                            'tipo'        => $record['punch_state'] ?? 0,
                            'estado'      => 0,
                            'raw_data'    => $record,
                            'procesado'   => false,
                        ]);
                        $nuevos++;
                    }
                }

                $pagina++;
            } while (!empty($data['next']));

            $syncLog->update([
                'finalizado_en'    => now(),
                'estado'           => 'completado',
                'registros_leidos' => $totalLeidos,
                'registros_nuevos' => $nuevos,
            ]);

            $location->update([
                'ultima_sync'    => now(),
                'sync_estado'    => 'ok',
                'sync_error_msg' => null,
            ]);

        } catch (\Exception $e) {
            $syncLog->update([
                'finalizado_en' => now(),
                'estado'        => 'error',
                'error_mensaje' => $e->getMessage(),
            ]);

            $location->update([
                'sync_estado'    => 'error',
                'sync_error_msg' => $e->getMessage(),
            ]);
        }

        return $syncLog;
    }

    public function importEmployees(): array
    {
        $this->token = $this->getToken();

        $response = Http::withHeaders([
            'Authorization' => "JWT {$this->token}",
            'Content-Type'  => 'application/json',
        ])->get("{$this->baseUrl}/personnel/api/employees/", [
            'page_size' => 1000,
        ]);

        return $response->json('data') ?? [];
    }
}