<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Employee;
use App\Models\AttendanceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZKTecoADMSController extends Controller
{
    private function getLocationBySN(string $sn): ?Location
    {
        return Location::where('reloj_sn', $sn)
            ->where('reloj_activo', true)
            ->first();
    }

    public function handshake(Request $request)
    {
        $sn = $request->query('SN', '');
        Log::info("[ADMS] Handshake desde reloj SN={$sn}");

        $body = implode("\r\n", [
            "GET OPTION FROM: {$sn}",
            "ATTLOGStamp=9999",
            "OPERLOGStamp=9999",
            "ATTPHOTOStamp=9999",
            "ErrorDelay=30",
            "Delay=10",
            "TransTimes=00:00;14:05",
            "TransInterval=1",
            "TransFlag=TransData AttLog OpLog EnrollUser ChgUser EnrollFP ChgFP UserPic",
            "TimeZone=-5",
            "Realtime=1",
            "Encrypt=None",
            "ServerName=time.google.com",
            "NTPServer=time.google.com",
        ]);

        return response($body, 200)->header('Content-Type', 'text/plain');
    }

    public function receiveData(Request $request)
    {
        $sn      = $request->query('SN', '');
        $table   = $request->query('table', '');
        $content = $request->getContent();

        Log::info("[ADMS] POST cdata SN={$sn} table={$table}", ['body' => substr($content, 0, 500)]);

        if (strtolower($table) === 'attlog') {
            $location = $this->getLocationBySN($sn);

            if ($location) {
                $nuevos = $this->parseAndStore($content, $location);
                Log::info("[ADMS] Guardados {$nuevos} registros nuevos para sede {$location->nombre}");

                $location->update([
                    'ultima_sync'    => now(),
                    'sync_estado'    => 'ok',
                    'sync_error_msg' => null,
                ]);
            } else {
                Log::warning("[ADMS] No se encontró sede para SN={$sn}");
            }
        }

        return response("OK", 200)->header('Content-Type', 'text/plain');
    }

    public function getRequest(Request $request)
    {
        $sn = $request->query('SN', '');
        Log::info("[ADMS] getrequest SN={$sn}");

        $location = $this->getLocationBySN($sn);

        if (!$location) {
            Log::warning("[ADMS] No se encontró sede para SN={$sn}");
            return response("OK", 200)->header('Content-Type', 'text/plain');
        }

        $employees = Employee::where('location_id', $location->id)
            ->where('active', true)
            ->whereNotNull('reloj_id')
            ->get();

        if ($employees->isEmpty()) {
            return response("OK", 200)->header('Content-Type', 'text/plain');
        }

        $commands = [];
        foreach ($employees as $emp) {
            $pin  = $emp->reloj_id;
            $name = mb_substr($emp->nombres . ' ' . $emp->apellidos, 0, 24);
            $commands[] = "C:{$emp->id}:DATA UPDATE USERINFO PIN={$pin}\tName={$name}\tPrivilege=0\tPassword=\tCard=\tPIN2={$pin}\tTZ=1\tVerify=0\tViceCard=";
        }

        $body = implode("\r\n", $commands);
        Log::info("[ADMS] Enviando " . count($commands) . " usuarios al reloj SN={$sn} sede={$location->nombre}");

        return response($body, 200)->header('Content-Type', 'text/plain');
    }

    public function deviceCmd(Request $request)
    {
        $content = $request->getContent();
        Log::info("[ADMS] devicecmd recibido", ['body' => substr($content, 0, 300)]);
        return response("OK", 200)->header('Content-Type', 'text/plain');
    }

    private function parseAndStore(string $content, Location $location): int
    {
        $nuevos = 0;
        $lines  = preg_split('/\r?\n/', trim($content));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, 'ATTLOG')) continue;

            $parts = explode("\t", $line);
            if (count($parts) < 4) continue;

            $pin      = trim($parts[0]);
            $datetime = trim($parts[1]);
            $inout    = isset($parts[3]) ? (int) trim($parts[3]) : 0;

            if (empty($pin) || empty($datetime)) continue;
            if ($datetime < '2026-01-01 00:00:00') continue;

            $existe = AttendanceLog::where('location_id', $location->id)
                ->where('reloj_id', $pin)
                ->where('timestamp', $datetime)
                ->exists();

            if (!$existe) {
                AttendanceLog::create([
                    'location_id' => $location->id,
                    'reloj_uid'   => 0,
                    'reloj_id'    => $pin,
                    'timestamp'   => $datetime,
                    'tipo'        => $inout,
                    'estado'      => 0,
                    'raw_data'    => ['raw_line' => $line],
                    'procesado'   => false,
                    'created_at'  => now(),
                ]);
                $nuevos++;
            }
        }

        return $nuevos;
    }
}
