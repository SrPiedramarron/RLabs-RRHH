<?php

namespace App\Services;

use App\Models\Location;
use App\Models\AttendanceLog;
use App\Models\SyncLog;

class ZKTecoSDKService
{
    private $socket = null;
    private string $ip;
    private int $port;
    private int $sessionId = 0;
    private int $replyId = 0;

    // Comandos ZKTeco
    const CMD_CONNECT        = 1000;
    const CMD_EXIT           = 1001;
    const CMD_ENABLEDEVICE   = 1002;
    const CMD_DISABLEDEVICE  = 1003;
    const CMD_RESTART        = 1004;
    const CMD_POWEROFF       = 1005;
    const CMD_SLEEP          = 1006;
    const CMD_RESUME         = 1007;
    const CMD_ATTLOG_RRQ     = 1503;
    const CMD_CLEAR_ATTLOG   = 1502;
    const CMD_DATA_WRRQ      = 1500;
    const CMD_DATA_RDY       = 1501;
    const CMD_ACK_OK         = 2000;
    const CMD_ACK_ERROR      = 2001;
    const CMD_ACK_DATA       = 2002;
    const CMD_PREPARE_DATA   = 1502;
    const CMD_DATA           = 1501;

    public function syncLocation(Location $location): SyncLog
    {
        $syncLog = SyncLog::create([
            'location_id' => $location->id,
            'iniciado_en' => now(),
            'estado'      => 'ejecutando',
        ]);

        try {
            $this->ip   = $location->reloj_ip;
            $this->port = $location->reloj_puerto;

            $this->connect();
            $registros = $this->getAttendance();
            $this->disconnect();

            $nuevos      = 0;
            $totalLeidos = count($registros);

            foreach ($registros as $record) {
                $existe = AttendanceLog::where('location_id', $location->id)
                    ->where('reloj_id', $record['id'])
                    ->where('timestamp', $record['timestamp'])
                    ->exists();

                if (!$existe) {
                    AttendanceLog::create([
                        'location_id' => $location->id,
                        'reloj_uid'   => $record['uid'],
                        'reloj_id'    => $record['id'],
                        'timestamp'   => $record['timestamp'],
                        'tipo'        => $record['type'] ?? 0,
                        'estado'      => 0,
                        'raw_data'    => $record,
                        'procesado'   => false,
                    ]);
                    $nuevos++;
                }
            }

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
            try { $this->disconnect(); } catch (\Throwable) {}

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

    private function connect(): void
    {
        $this->socket = fsockopen($this->ip, $this->port, $errno, $errstr, 10);
        if (!$this->socket) {
            throw new \Exception("No se pudo conectar al reloj {$this->ip}:{$this->port} - {$errstr}");
        }
        stream_set_timeout($this->socket, 10);

// Limpiar buffer inicial por si hay sesion colgada
stream_set_blocking($this->socket, false);
$garbage = fread($this->socket, 1024);
stream_set_blocking($this->socket, true);
stream_set_timeout($this->socket, 15);

        $this->sessionId = 0;
        $this->replyId   = 0;

        $response = $this->sendCommand(self::CMD_CONNECT, '');
        if ($response === false) {
            throw new \Exception("El reloj no respondió al comando de conexión");
        }

        $this->sessionId = unpack('S', substr($response, 4, 2))[1];
    }

    private function disconnect(): void
    {
        if ($this->socket) {
            $this->sendCommand(self::CMD_EXIT, '');
            fclose($this->socket);
            $this->socket = null;
        }
    }

    private function sendCommand(int $command, string $data = ''): string|false
    {
        $this->replyId++;
        $length = strlen($data);

        // Header: command(2) + checksum(2) + sessionId(2) + replyId(2)
        $header = pack('SSSS', $command, 0, $this->sessionId, $this->replyId);
        $packet = $header . $data;

        // Calcular checksum
        $checksum = $this->calculateChecksum($packet);
        $packet   = substr_replace($packet, pack('S', $checksum), 2, 2);

        // Agregar magic bytes
        $buf = pack('H*', 'FAAF') . $packet;

        fwrite($this->socket, $buf);

        $response = fread($this->socket, 1024);
        if ($response === false || strlen($response) < 8) {
            return false;
        }

        return $response;
    }

    private function calculateChecksum(string $data): int
    {
        $chksum = 0;
        $len    = strlen($data);
        $i      = 0;
        while ($len > 1) {
            $chksum += unpack('S', substr($data, $i, 2))[1];
            $i      += 2;
            $len    -= 2;
        }
        if ($len) {
            $chksum += ord($data[$i]);
        }
        while ($chksum >> 16) {
            $chksum = ($chksum & 0xFFFF) + ($chksum >> 16);
        }
        return ~$chksum & 0xFFFF;
    }

    private function getAttendance(): array
    {
        // Solicitar log de asistencia
        $response = $this->sendCommand(self::CMD_ATTLOG_RRQ, '');
        if ($response === false) {
            throw new \Exception("Error al solicitar registros de asistencia");
        }

        $command = unpack('S', substr($response, 0, 2))[1];

        $rawData = '';

        if ($command == self::CMD_ACK_OK) {
            // Sin datos
            return [];
        }

        // Leer datos en chunks
        if ($command == 1501 || $command == 1502) {
            $size = unpack('V', substr($response, 8, 4))[1];

            // Leer todos los chunks
            while (strlen($rawData) < $size) {
                $chunk = fread($this->socket, 1024 + 8);
                if ($chunk === false || $chunk === '') break;
                $rawData .= substr($chunk, 8); // quitar header
            }
        }

        return $this->parseAttendanceData($rawData);
    }

    private function parseAttendanceData(string $data): array
    {
        $records = [];
        $len     = strlen($data);
        $i       = 0;

        while ($i + 40 <= $len) {
            $uid       = unpack('S', substr($data, $i, 2))[1];
            $empCode   = rtrim(substr($data, $i + 2, 9), "\0");
            $type      = ord($data[$i + 11]);
            $state     = ord($data[$i + 12]);
            $timestamp = $this->decodeTime(substr($data, $i + 13, 4));
            $i        += 40;

            if (empty($empCode) || empty($timestamp)) continue;
            // Solo importar desde 2026
            if ($timestamp < '2026-01-01 00:00:00') continue;

            $records[] = [
                'uid'       => $uid,
                'id'        => $empCode,
                'type'      => $type,
                'state'     => $state,
                'timestamp' => $timestamp,
            ];
        }

        return $records;
    }

    private function decodeTime(string $data): ?string
    {
        $t = unpack('V', $data)[1];
        if ($t == 0) return null;

        $second = $t % 60; $t = intdiv($t, 60);
        $minute = $t % 60; $t = intdiv($t, 60);
        $hour   = $t % 24; $t = intdiv($t, 24);
        $day    = $t % 31 + 1; $t = intdiv($t, 31);
        $month  = $t % 12 + 1; $t = intdiv($t, 12);
        $year   = $t + 2000;

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    }
}
