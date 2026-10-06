<?php

namespace App\Models;

use App\Mail\NuevaSolicitudMail;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Mail;

class Solicitud extends Model
{
    protected $table = 'solicitudes';

    protected $fillable = [
        'employee_id',
        'jefe_id',
        'jefe_resuelto_at',
        'comentario_jefe',
        'escalada_at',
        'company_id',
        'tipo',
        'estado',
        'fecha_inicio',
        'fecha_fin',
        'fecha_registro',
        'hora_entrada_solicitada',
        'hora_salida_solicitada',
        'motivo_suspension_plame',
        'motivo',
        'adjunto_path',
        'revisado_por',
        'revisado_at',
        'comentario_revision',
    ];

    protected $casts = [
        'fecha_inicio'   => 'date',
        'fecha_fin'      => 'date',
        'fecha_registro' => 'date',
        'revisado_at'    => 'datetime',
        'jefe_resuelto_at' => 'datetime',
        'escalada_at'    => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function jefe(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'jefe_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function getTipoLabelAttribute(): string
    {
        return match ($this->tipo) {
            'vacaciones'        => 'Vacaciones',
            'permiso'           => 'Permiso',
            'correccion_horas'  => 'Corrección de horas',
            default             => '—',
        };
    }

    public function getEstadoColorAttribute(): string
    {
        return match ($this->estado) {
            'pendiente' => 'warning',
            'pendiente_jefe' => 'info',
            'aprobada'  => 'success',
            'rechazada' => 'danger',
            default     => 'gray',
        };
    }

    protected static function booted(): void
    {
        static::created(function (Solicitud $solicitud) {
            if ($solicitud->estado === 'pendiente_jefe') {
                app(\App\Services\SolicitudJefeService::class)->notificarJefe($solicitud);

                return;
            }

            $solicitud->notificarRrhh();
        });
    }

    /** Avisa a RRHH (notificación interna y correo) de que hay una solicitud por revisar. */
    public function notificarRrhh(): void
    {
            $destinatarios = User::where('role', 'superadmin')
                ->orWhereNull('company_id')
                ->orWhere('company_id', $this->company_id)
                ->get();

            if ($destinatarios->isEmpty()) {
                return;
            }

            Notification::make()
                ->title('Nueva solicitud: ' . $this->tipo_label)
                ->body($this->employee->nombre_completo . ($this->motivo ? ' — ' . \Illuminate\Support\Str::limit($this->motivo, 80) : ''))
                ->icon('heroicon-o-inbox-arrow-down')
                ->actions([
                    \Filament\Notifications\Actions\Action::make('ver')
                        ->label('Ver solicitud')
                        ->url(\App\Filament\Resources\SolicitudResource::getUrl('index', ['tableFilters[estado][value]' => 'pendiente']))
                        ->button(),
                ])
                ->sendToDatabase($destinatarios);

            // Correo a RRHH (genérico por empresa, igual patrón que los
            // avisos de contratos por vencer) — pedido explícito oct. 2026
            // de que las solicitudes lleguen también por correo, no solo
            // como notificación dentro del sistema.
            $company = $this->company;
            if ($company?->email) {
                Mail::to($company->email)
                    ->cc($company->email_cc ? [$company->email_cc] : [])
                    ->queue(new NuevaSolicitudMail($this));
            }
    }
}
