<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FacialValidationService
{
    private string $baseUrl;
    private string $secret;
    private int    $timeout;

    public function __construct()
    {
        $this->baseUrl = config('facial.url',     'http://127.0.0.1:5001');
        $this->secret  = config('facial.secret',  'rlabs_facial_2026');
        $this->timeout = config('facial.timeout', 15);
    }

    /**
     * Compara la foto de perfil del empleado con la selfie del checkin.
     *
     * @param  string $fotoPerfil   Ruta en storage (ej: empleados/fotos/abc.jpg)
     * @param  string $fotoCheckin  Ruta en storage (ej: checkins/2026/02/xyz.jpg)
     * @param  int    $employeeId   Para logging
     * @return array{
     *     match: bool,
     *     confianza: float|null,
     *     distancia: float|null,
     *     estado: string,       // 'aprobado'|'rechazado'|'sin_perfil'|'error'
     *     mensaje: string
     * }
     */
    public function comparar(string $fotoPerfil, string $fotoCheckin, int $employeeId = 0): array
    {
        // Verificar que el servicio esté disponible
        if (! $this->isAvailable()) {
            Log::warning("FacialService: servicio no disponible para employee_id={$employeeId}");
            return $this->errorResponse('Servicio de validación facial no disponible');
        }

        // Normalizar rutas — las fotos se guardan en storage/app/public/
        $fotoPerfilPath  = Storage::exists($fotoPerfil) ? $fotoPerfil : 'public/' . $fotoPerfil;
        $fotoCheckinPath = Storage::exists($fotoCheckin) ? $fotoCheckin : 'public/' . $fotoCheckin;

        // Verificar que las fotos existen en storage
        if (! Storage::disk('public')->exists($fotoPerfil) && ! Storage::exists($fotoPerfilPath)) {
            return [
                'match'     => false,
                'confianza' => null,
                'distancia' => null,
                'estado'    => 'sin_perfil',
                'mensaje'   => 'El empleado no tiene foto de perfil registrada',
            ];
        }

        if (! Storage::exists($fotoCheckinPath)) {
            return $this->errorResponse('No se encontró la foto del checkin');
        }

        try {
            // Convertir fotos a base64
            $perfilBase64  = base64_encode(
                Storage::disk('public')->exists($fotoPerfil)
                    ? Storage::disk('public')->get($fotoPerfil)
                    : Storage::get($fotoPerfilPath)
            );
            $checkinBase64 = base64_encode(
                Storage::disk('public')->exists($fotoCheckin)
                    ? Storage::disk('public')->get($fotoCheckin)
                    : Storage::get($fotoCheckinPath)
            );

            // Detectar mime type
            $perfilMime  = 'image/jpeg';
            $checkinMime = 'image/jpeg';

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/compare", [
                    'secret'       => $this->secret,
                    'foto_perfil'  => "data:{$perfilMime};base64,{$perfilBase64}",
                    'foto_checkin' => "data:{$checkinMime};base64,{$checkinBase64}",
                    'employee_id'  => $employeeId,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'match'     => (bool) ($data['match'] ?? false),
                    'confianza' => $data['confianza'] ?? null,
                    'distancia' => $data['distancia'] ?? null,
                    'estado'    => ($data['match'] ?? false) ? 'aprobado' : 'rechazado',
                    'mensaje'   => $data['mensaje'] ?? '',
                ];
            }

            // Error controlado del servicio (ej: no se detectó rostro)
            $error = $response->json();
            $codigo = $error['codigo'] ?? 'ERROR';

            Log::warning("FacialService: error en comparación employee_id={$employeeId}", $error);

            return [
                'match'     => false,
                'confianza' => null,
                'distancia' => null,
                'estado'    => $codigo === 'SIN_ROSTRO' ? 'rechazado' : 'error',
                'mensaje'   => $error['error'] ?? 'Error en validación facial',
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("FacialService: no se pudo conectar al servicio: {$e->getMessage()}");
            return $this->errorResponse('No se pudo conectar al servicio facial');

        } catch (\Exception $e) {
            Log::error("FacialService: excepción inesperada: {$e->getMessage()}");
            return $this->errorResponse('Error inesperado en validación facial');
        }
    }

    /**
     * Verifica si el microservicio Python está corriendo.
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(3)->get("{$this->baseUrl}/health");
            return $response->successful();
        } catch (\Exception) {
            return false;
        }
    }

    private function errorResponse(string $mensaje): array
    {
        return [
            'match'     => false,
            'confianza' => null,
            'distancia' => null,
            'estado'    => 'error',
            'mensaje'   => $mensaje,
        ];
    }
}
