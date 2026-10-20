<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\Schedule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use App\Filament\Traits\HasCompanyScope;

class EmployeeResource extends Resource
{
    use HasCompanyScope;
    protected static ?string $model         = Employee::class;
    protected static ?string $navigationIcon  = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Trabajadores';
    protected static ?string $modelLabel      = 'Trabajador';
    protected static ?string $pluralModelLabel = 'Trabajadores';
    protected static ?int    $navigationSort  = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Trabajador')
                ->columnSpanFull()
                ->tabs([

                    // ── DATOS PERSONALES ──────────────────────────────────
                    Forms\Components\Tabs\Tab::make('Datos Personales')
                        ->icon('heroicon-o-identification')
                        ->schema([
                            Forms\Components\Select::make('company_id')
                                ->label('Empresa')
                                ->relationship('company', 'razon_social')
                                ->required()
                                ->searchable()
                                ->live()
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('nombres')
                                ->label('Nombres')
                                ->required()
                                ->maxLength(100),

                            Forms\Components\TextInput::make('apellidos')
                                ->label('Apellidos')
                                ->required()
                                ->maxLength(100),

                            Forms\Components\TextInput::make('dni')
                                ->label('DNI')
                                ->required()
                                ->length(8)
                                ->numeric(),

                            Forms\Components\TextInput::make('email')
                                ->label('Correo electrónico (avisos)')
                                ->email()
                                ->nullable()
                                ->maxLength(150)
                                ->helperText('Opcional. Si se completa, aquí llegan los avisos de sus solicitudes (vacaciones/permisos/correcciones).'),

                            Forms\Components\DatePicker::make('fecha_nacimiento')
                                ->label('Fecha de nacimiento')
                                ->displayFormat('d/m/Y')
                                ->native(false),

                            Forms\Components\Select::make('sexo')
                                ->label('Sexo')
                                ->options(['M' => 'Masculino', 'F' => 'Femenino'])
                                ->native(false),

                            Forms\Components\TextInput::make('direccion')
                                ->label('Dirección (domicilio)')
                                ->maxLength(255)
                                ->columnSpan(2),
                            Forms\Components\Select::make('ubigeo_departamento')
                                ->label('Departamento')
                                ->options(fn () => \App\Support\Ubigeo::departamentos())
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set) {
                                    $set('ubigeo_provincia', null);
                                    $set('ubigeo_distrito', null);
                                }),

                            Forms\Components\Select::make('ubigeo_provincia')
                                ->label('Provincia')
                                ->options(fn (Forms\Get $get) => \App\Support\Ubigeo::provincias($get('ubigeo_departamento')))
                                ->searchable()
                                ->live()
                                ->disabled(fn (Forms\Get $get) => ! $get('ubigeo_departamento'))
                                ->afterStateUpdated(fn (Forms\Set $set) => $set('ubigeo_distrito', null)),

                            Forms\Components\Select::make('ubigeo_distrito')
                                ->label('Distrito')
                                ->options(fn (Forms\Get $get) => \App\Support\Ubigeo::distritos($get('ubigeo_provincia')))
                                ->searchable()
                                ->disabled(fn (Forms\Get $get) => ! $get('ubigeo_provincia')),

                            Forms\Components\TextInput::make('celular')
                                ->label('Celular')
                                ->tel()
                                ->maxLength(20),

                            Forms\Components\TextInput::make('correo_corporativo')
                                ->label('Correo corporativo')
                                ->email()
                                ->maxLength(150),

                            Forms\Components\TextInput::make('correo_personal')
                                ->label('Correo personal')
                                ->email()
                                ->maxLength(150),

                            Forms\Components\FileUpload::make('foto_perfil')
                                ->label('Foto de Perfil')
                                ->helperText('Requerida para validación facial en marcado remoto. Foto frontal con buena iluminación.')
                                ->image()
                                ->imageEditor()
                                ->imageCropAspectRatio('1:1')
                                ->imageResizeTargetWidth('400')
                                ->imageResizeTargetHeight('400')
                                ->directory('empleados/fotos')
                                ->visibility('private')
                                ->maxSize(2048)
                                ->columnSpan(2)
                                ->avatar(),
                        ])->columns(2),

                    // ── DATOS LABORALES ───────────────────────────────────
                    Forms\Components\Tabs\Tab::make('Datos Laborales')
                        ->icon('heroicon-o-briefcase')
                        ->schema([
                            Forms\Components\Select::make('location_id')
                                ->label('Sede')
                                ->relationship('location', 'nombre')
                                ->required()
                                ->searchable(),

                            Forms\Components\Select::make('department_id')
                                ->label('Área')
                                ->relationship('department', 'nombre')
                                ->searchable()
                                ->nullable(),

                            Forms\Components\TextInput::make('centro_costos')
                                ->label('Centro de costos')
                                ->maxLength(100),

                            Forms\Components\Select::make('schedule_id')
                                ->label('Horario Principal')
                                ->relationship('schedule', 'nombre')
                                ->required(fn (Forms\Get $get) => $get('tipo_vinculo') !== 'locador')
                                ->visible(fn (Forms\Get $get) => $get('tipo_vinculo') !== 'locador')
                                ->searchable()
                                ->helperText('Horario de lunes a viernes'),

                            Forms\Components\Select::make('schedules')
                                ->label('Horarios Adicionales')
                                ->relationship('schedules', 'nombre')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->helperText('Ej: horario de sábados u otros turnos especiales'),

                            Forms\Components\Select::make('sedes_adicionales')
                                ->label('Sedes Adicionales')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->options(fn () => \App\Models\Location::pluck('nombre', 'id'))
                                ->helperText('Sedes donde este trabajador también puede marcar asistencia, además de su sede principal.')
                                ->afterStateHydrated(function (Forms\Components\Select $component, $record) {
                                    if ($record) {
                                        $component->state(
                                            $record->devices()
                                                ->where('location_id', '!=', $record->location_id)
                                                ->pluck('location_id')
                                                ->toArray()
                                        );
                                    }
                                }),

                            Forms\Components\TextInput::make('codigo_empleado')
                                ->label('Código')
                                ->maxLength(20),

                            Forms\Components\Select::make('tipo_vinculo')
                                ->label('Tipo de vínculo')
                                ->options(['planilla' => 'Planilla', 'locador' => 'Locador de servicios (recibo por honorarios)'])
                                ->default('planilla')
                                ->required()
                                ->live()
                                ->helperText('Los locadores no entran a planilla, asistencia, vacaciones ni beneficios; se les paga un honorario fijo por quincena.'),

                            Forms\Components\Select::make('frecuencia_pago')
                                ->label('Frecuencia de pago')
                                ->options(['quincenal' => 'Quincenal (adelanto el 15 y saldo a fin de mes)', 'mensual' => 'Mensual (todo a fin de mes)'])
                                ->placeholder('Según la empresa')
                                ->native(false)
                                ->helperText('Vacío: planilla sigue la configuración de la empresa; locadores, quincenal.'),

                            Forms\Components\TextInput::make('honorario_mensual')
                                ->label('Honorario mensual (S/)')
                                ->numeric()
                                ->prefix('S/')
                                ->visible(fn (Forms\Get $get) => $get('tipo_vinculo') === 'locador')
                                ->helperText('Se paga la mitad cada quincena.'),

                            Forms\Components\TextInput::make('ruc_locador')
                                ->label('RUC del locador')
                                ->maxLength(11)
                                ->visible(fn (Forms\Get $get) => $get('tipo_vinculo') === 'locador'),

                            Forms\Components\Toggle::make('suspension_4ta')
                                ->label('Tiene suspensión de retenciones de 4.ª categoría')
                                ->visible(fn (Forms\Get $get) => $get('tipo_vinculo') === 'locador')
                                ->helperText('Con suspensión vigente no se retiene el 8%.'),
                            Forms\Components\Select::make('jefe_directo_id')
                                ->label('Jefe directo')
                                ->helperText('Aprueba primero sus vacaciones y permisos desde la app. Sin jefe, van directo a RRHH.')
                                ->options(fn ($record) => \App\Models\Employee::where('active', true)
                                    ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                    ->orderBy('apellidos')
                                    ->get()
                                    ->mapWithKeys(fn ($e) => [$e->id => $e->apellidos . ', ' . $e->nombres]))
                                ->searchable()
                                ->nullable(),

                            Forms\Components\TextInput::make('cargo')
                                ->label('Cargo')
                                ->maxLength(100),

                            Forms\Components\DatePicker::make('fecha_ingreso')
                                ->label('Fecha de Ingreso')
                                ->required()
                                ->displayFormat('d/m/Y'),

                            Forms\Components\DatePicker::make('fecha_fin_contrato')
                                ->label('Fecha fin de contrato')
                                ->displayFormat('d/m/Y')
                                ->helperText('Fin del contrato VIGENTE. Al crear al trabajador, normalmente es igual a la Fecha de Cese. Para renovar, usa el botón "Renovar contrato" más abajo — actualiza esta fecha y la de cese automáticamente, y deja constancia en el historial.')
                                ->nullable(),

                            Forms\Components\Select::make('tipo_contrato')
                                ->label('Tipo de Contrato')
                                ->options([
                                    'indeterminado' => 'Indeterminado',
                                    'inicio_incremento_actividad' => 'Inicio o incremento de actividad',
                                    'necesidad_mercado' => 'Necesidad de mercado',
                                    'obra_servicio_especifico' => 'Obra determinada o servicio específico',
                                    'locacion_servicios' => 'De locación de servicios',
                                ])
                                ->native(false)
                                ->nullable(),

                            Forms\Components\DatePicker::make('fecha_cese')
                                ->label('Fecha de Cese')
                                ->displayFormat('d/m/Y')
                                ->nullable(),

                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('renovar_contrato')
                                    ->label('Renovar contrato')
                                    ->icon('heroicon-o-arrow-path')
                                    ->color('warning')
                                    ->visible(fn (?Employee $record) => $record !== null)
                                    ->form([
                                        Forms\Components\Placeholder::make('fecha_actual')
                                            ->label('Fin de contrato actual')
                                            ->content(fn (?Employee $record) => $record?->fecha_fin_contrato?->format('d/m/Y') ?? 'Sin fecha registrada'),

                                        Forms\Components\DatePicker::make('fecha_fin_nueva')
                                            ->label('Nueva fecha fin de contrato')
                                            ->displayFormat('d/m/Y')
                                            ->required(),

                                        Forms\Components\TextInput::make('observacion')
                                            ->label('Observación (opcional)')
                                            ->maxLength(255),
                                    ])
                                    ->action(function (Employee $record, array $data): void {
                                        $numeroRenovacion = (int) $record->renovacionesContrato()->max('numero_renovacion') + 1;

                                        \App\Models\ContractRenewal::create([
                                            'employee_id' => $record->id,
                                            'numero_renovacion' => $numeroRenovacion,
                                            'fecha_fin_anterior' => $record->fecha_fin_contrato,
                                            'fecha_fin_nueva' => $data['fecha_fin_nueva'],
                                            'observacion' => $data['observacion'] ?? null,
                                            'renovado_por' => \Illuminate\Support\Facades\Auth::id(),
                                            'renovado_at' => now(),
                                        ]);

                                        $record->update([
                                            'fecha_fin_contrato' => $data['fecha_fin_nueva'],
                                            'fecha_cese' => $data['fecha_fin_nueva'],
                                        ]);

                                        $record->refresh();
                                    })
                                    ->successNotificationTitle('✅ Contrato renovado.'),
                            ])->visibleOn('edit'),

                            Forms\Components\Toggle::make('active')
                                ->label('Activo')
                                ->default(true),

                            Forms\Components\Section::make('Datos Bancarios')
                                ->schema([
                                    Forms\Components\TextInput::make('banco')
                                        ->label('Banco')
                                        ->maxLength(100),

                                    Forms\Components\TextInput::make('numero_cuenta')
                                        ->label('Número de Cuenta')
                                        ->maxLength(30),

                                    Forms\Components\TextInput::make('cci')
                                        ->label('CCI')
                                        ->maxLength(20)
                                        ->helperText('Código de Cuenta Interbancario, 20 dígitos.'),

                                    Forms\Components\TextInput::make('banco_cts')
                                        ->label('Banco (CTS)')
                                        ->maxLength(100)
                                        ->helperText('Por ley, la cuenta CTS puede ser de un banco distinto al de la remuneración.'),

                                    Forms\Components\TextInput::make('numero_cuenta_cts')
                                        ->label('N° de Cuenta CTS')
                                        ->maxLength(30),

                                    Forms\Components\TextInput::make('cci_cts')
                                        ->label('CCI CTS')
                                        ->maxLength(20),
                                ])
                                ->columns(3)
                                ->columnSpan(2),
                        ])->columns(2),

                    // ── VACACIONES ─────────────────────────────────────────
                    Forms\Components\Tabs\Tab::make('Vacaciones')
                        ->icon('heroicon-o-sun')
                        ->schema([
                            Forms\Components\DatePicker::make('fecha_ultima_vacacion')
                                ->label('Fecha de la última vacación')
                                ->displayFormat('d/m/Y')
                                ->helperText('Fecha de vuelta de la última vacación tomada. El saldo se calcula desde aquí (2.5 días/mes). Si está vacío, se calcula desde la Fecha de Ingreso.'),

                            Forms\Components\TextInput::make('dias_tomados')
                                ->label('Días tomados')
                                ->numeric()
                                ->default(0)
                                ->helperText('Contador histórico acumulado. Se actualiza solo al usar "Registrar vacaciones" en Control de Vacaciones; también editable a mano si hace falta un ajuste.'),

                            Forms\Components\Placeholder::make('nota_historial')
                                ->label('')
                                ->content('El detalle de cada vacación tomada está en la pestaña "Historial de Vacaciones" (abajo, junto a Documentos), visible solo al editar un trabajador ya guardado.')
                                ->columnSpan(2),
                        ])->columns(2),

                    // ── PLANILLA Y SUNAFIL ─────────────────────────────────
                    Forms\Components\Tabs\Tab::make('Planilla y SUNAFIL')
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            Forms\Components\Toggle::make('exonerado_registro')
                                ->label('Exonerado de Registro')
                                ->helperText('Trabajadores de dirección o sin fiscalización inmediata')
                                ->live(),

                            Forms\Components\TextInput::make('motivo_exoneracion')
                                ->label('Motivo de Exoneración')
                                ->maxLength(200)
                                ->visible(fn(Get $get) => $get('exonerado_registro')),

                            Forms\Components\TextInput::make('jornada_ref_horas_dia')
                                ->label('Jornada referencial lun-vie (horas por día)')
                                ->numeric()->minValue(0)->maxValue(24)->step(0.25)->default(8.5)
                                ->helperText('Para el archivo de PLAME: como no marca asistencia, sus horas ordinarias se calculan solas por los días del periodo de corte (26 al 25), sin feriados ni vacaciones (las vacaciones se miran por mes calendario). Al cese, hasta su fecha de cese.')
                                ->visible(fn(Get $get) => $get('exonerado_registro')),

                            Forms\Components\TextInput::make('jornada_ref_horas_sabado')
                                ->label('Jornada referencial sábado (horas)')
                                ->numeric()->minValue(0)->maxValue(24)->step(0.25)->default(4)
                                ->visible(fn(Get $get) => $get('exonerado_registro')),

                            Forms\Components\TextInput::make('jornada_ref_sabados_mes')
                                ->label('Sábados que cuentan por mes (1 por quincena, máx. 2)')
                                ->numeric()->minValue(0)->maxValue(5)->default(2)
                                ->visible(fn(Get $get) => $get('exonerado_registro')),

                            Forms\Components\TextInput::make('sueldo_base')
                                ->label('Sueldo Base (S/)')
                                ->numeric()
                                ->prefix('S/')
                                ->minValue(0)
                                ->default(0)
                                ->required(),

                            Forms\Components\Select::make('sistema_pensiones')
                                ->label('Sistema de Pensiones')
                                ->options([
                                    'onp'           => 'ONP (13%)',
                                    'afp_prima'     => 'AFP Prima (10.23%)',
                                    'afp_integra'   => 'AFP Integra (10.23%)',
                                    'afp_habitat'   => 'AFP Habitat (10.47%)',
                                    'afp_profuturo' => 'AFP Profuturo (10.84%)',
                                ])
                                ->required()
                                ->default('onp')
                                ->native(false),

                            Forms\Components\TextInput::make('cuspp')
                                ->label('CUSPP')
                                ->maxLength(12)
                                ->dehydrateStateUsing(fn ($state) => $state ? strtoupper(trim($state)) : null)
                                ->helperText('Código único del afiliado al SPP (12 caracteres). Va en el archivo de AFPnet y en la boleta. Vacío si aún no tiene (ingreso reciente).')
                                ->visible(fn (Forms\Get $get) => str_starts_with($get('sistema_pensiones') ?? '', 'afp_')),

                            Forms\Components\Toggle::make('aplica_comision_flujo_afp')
                                ->label('Paga comisión sobre flujo AFP')
                                ->default(true)
                                ->helperText('Activo = afiliado a su AFP ANTES de feb-2013 (comisión flujo completa vía planilla). Desactivar si se afilió DESPUÉS (comisión mixta — la AFP cobra su parte directo de la cuenta, no vía planilla). El aporte obligatorio y la prima de seguro siempre aplican, sin importar este switch.')
                                ->visible(fn (Forms\Get $get) => str_starts_with($get('sistema_pensiones') ?? '', 'afp_')),

                            Forms\Components\Toggle::make('aplica_5ta_categoria')
                                ->label('Aplica descuento 5ta categoría')
                                ->helperText('Impuesto a la renta para sueldos altos')
                                ->default(false),

                            Forms\Components\Toggle::make('aplica_comision')
                                ->label('Aplica comisiones')
                                ->helperText('Incluir comisiones de ventas en la liquidación mensual')
                                ->live()
                                ->default(false),

                            Forms\Components\Select::make('tipo_base_comision')
                                ->label('Base de cálculo')
                                ->options([
                                    'cartera_propia' => 'Cartera propia (solo sus ventas)',
                                    'total_empresa'  => 'Total de la empresa (todas las ventas del periodo)',
                                ])
                                ->default('cartera_propia')
                                ->native(false)
                                ->visible(fn (Forms\Get $get) => $get('aplica_comision')),

                            Forms\Components\TextInput::make('porcentaje_comision')
                                ->label('Porcentaje de comisión (%)')
                                ->numeric()
                                ->step(0.01)
                                ->suffix('%')
                                ->helperText('Se usa si el trabajador NO tiene escalas configuradas (pestaña "Escalas de Comisión"). Si se deja vacío, se usa 1.5% por defecto.')
                                ->formatStateUsing(fn ($state) => $state !== null ? round($state * 100, 4) : null)
                                ->dehydrateStateUsing(fn ($state) => filled($state) ? $state / 100 : null)
                                ->visible(fn (Forms\Get $get) => $get('aplica_comision')),

                            Forms\Components\Toggle::make('horas_por_marcacion')
                                ->label('Horas = de la entrada a la salida marcadas')
                                ->helperText('Para jornadas cortas o a otra hora que el horario asignado (ej. 3 horas diarias): las horas ordinarias son exactamente salida menos entrada, sin descontar refrigerio, sin recortar al horario, sin tardanza ni horas extra.')
                                ->default(false),

                            Forms\Components\Toggle::make('compensa_horas_extras')
                                ->label('Compensa horas extras')
                                ->helperText('Activo = el trabajador NO cobra sus horas extra, las compensa saliendo tarde y entrando tarde otro día. Las horas siguen registrándose en asistencia, pero no se pagan en la liquidación.')
                                ->default(false),

                            Forms\Components\Toggle::make('aplica_asignacion_familiar')
                                ->label('Asignación Familiar')
                                ->helperText('Activar si el trabajador tiene hijos menores de 18 años (o hasta 24 si estudian). Monto: 10% de la RMV, calculado automáticamente.')
                                ->default(false),

                            Forms\Components\TextInput::make('movilidad_mensual_maxima')
                                ->label('Movilidad — máximo mensual (S/)')
                                ->numeric()
                                ->prefix('S/')
                                ->default(0)
                                ->helperText('Monto MÁXIMO si trabaja todo el mes. Se prorratea automáticamente según días efectivamente trabajados: (máximo ÷ días laborables) × días trabajados. 0 = no aplica.'),

                            Forms\Components\TextInput::make('monto_eps_mensual_con_igv')
                                ->label('Plan EPS mensual con IGV (S/)')
                                ->numeric()
                                ->prefix('S/')
                                ->default(0)
                                ->helperText('Costo del plan EPS (Sanitas Perú) tal como factura el proveedor, CON IGV. 0 = no tiene EPS, va 100% por EsSalud.'),

                            Forms\Components\TextInput::make('bono_encargatura')
                                ->label('Bono por encargatura (S/)')
                                ->numeric()
                                ->prefix('S/')
                                ->default(0)
                                ->helperText('Monto fijo mensual por encargatura de cargo. A diferencia de movilidad, SÍ afecta EsSalud y AFP/ONP. Código PLAME 1007.'),

                            Forms\Components\TextInput::make('seguro_vida_mensual')
                                ->label('Seguro vida ley — monto mensual (S/)')
                                ->numeric()
                                ->prefix('S/')
                                ->default(0)
                                ->helperText('Prima anual que paga la empresa ÷ 12. Cambia solo cuando se renueva la póliza. Aplica desde 3 meses de antigüedad. 100% costo de la empresa, no se descuenta al trabajador.'),
                        ])->columns(2),

                    // ── RELOJ Y ACCESO REMOTO ──────────────────────────────
                    Forms\Components\Tabs\Tab::make('Reloj y Acceso Remoto')
                        ->icon('heroicon-o-device-phone-mobile')
                        ->schema([
                            Forms\Components\TextInput::make('reloj_uid')
                                ->label('UID en el Reloj')
                                ->numeric()
                                ->nullable(),

                            Forms\Components\TextInput::make('reloj_id')
                                ->label('ID en el Reloj')
                                ->numeric()
                                ->nullable(),

                            Forms\Components\Section::make('Acceso Remoto (App Móvil)')
                                ->description('Credenciales para que el trabajador pueda marcar entrada/salida desde su celular.')
                                ->icon('heroicon-o-device-phone-mobile')
                                ->schema([
                                    Forms\Components\Placeholder::make('estado_acceso')
                                        ->label('Estado')
                                        ->content(function (?Employee $record): string {
                                            if (! $record) return 'El trabajador aún no ha sido creado.';
                                            if (! $record->credential) return '⚪ Sin acceso remoto configurado.';
                                            return $record->credential->active
                                                ? '🟢 Acceso activo'
                                                : '🔴 Acceso desactivado';
                                        })
                                        ->visibleOn('edit'),

                                    // Estos 3 controles se manejan como acciones independientes
                                    // (no como campos del formulario general) porque el guardado
                                    // normal ("Guardar cambios") no los persistía de forma
                                    // confiable: el toggle "Acceso activo" se enviaba en false
                                    // aunque se viera activado, y el campo de contraseña nueva
                                    // tenía dehydrated(false) por lo que nunca llegaba a guardarse.
                                    // Bug reportado por RRHH: "cada vez que se edita la ficha se
                                    // pierde el acceso y hay que resetear la contraseña".
                                    Forms\Components\Actions::make([
                                        Forms\Components\Actions\Action::make('cambiar_password')
                                            ->label('Cambiar contraseña')
                                            ->icon('heroicon-o-key')
                                            ->color('gray')
                                            ->visible(fn (?Employee $record) => $record !== null)
                                            ->form([
                                                Forms\Components\TextInput::make('password_nueva')
                                                    ->label('Contraseña nueva')
                                                    ->password()
                                                    ->revealable()
                                                    ->minLength(6)
                                                    ->required(),
                                            ])
                                            ->action(function (Employee $record, array $data): void {
                                                EmployeeCredential::updateOrCreate(
                                                    ['employee_id' => $record->id],
                                                    ['password' => Hash::make($data['password_nueva'])]
                                                );
                                                $record->refresh();
                                            })
                                            ->successNotificationTitle('✅ Contraseña actualizada.'),

                                        Forms\Components\Actions\Action::make('resetear_dni_ficha')
                                            ->label('Resetear al DNI')
                                            ->icon('heroicon-o-arrow-path')
                                            ->color('warning')
                                            ->requiresConfirmation()
                                            ->visible(fn (?Employee $record) => $record !== null)
                                            ->modalDescription(fn (Employee $record) => "Se reseteará la contraseña a su DNI ({$record->dni}).")
                                            ->action(function (Employee $record): void {
                                                EmployeeCredential::updateOrCreate(
                                                    ['employee_id' => $record->id],
                                                    ['password' => Hash::make($record->dni)]
                                                );
                                                $record->refresh();
                                            })
                                            ->successNotificationTitle('✅ Contraseña reseteada al DNI.'),

                                        Forms\Components\Actions\Action::make('toggle_acceso')
                                            ->label(fn (?Employee $record) => $record?->credential?->active ? 'Desactivar acceso' : 'Activar acceso')
                                            ->icon(fn (?Employee $record) => $record?->credential?->active ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
                                            ->color(fn (?Employee $record) => $record?->credential?->active ? 'danger' : 'success')
                                            ->visible(fn (?Employee $record) => $record !== null)
                                            ->requiresConfirmation()
                                            ->action(function (Employee $record): void {
                                                $activoAhora = (bool) $record->credential?->active;
                                                EmployeeCredential::updateOrCreate(
                                                    ['employee_id' => $record->id],
                                                    array_merge(
                                                        ['active' => ! $activoAhora],
                                                        $record->credential ? [] : ['password' => Hash::make($record->dni)]
                                                    )
                                                );
                                                $record->refresh();
                                            })
                                            ->successNotificationTitle('✅ Acceso actualizado.'),
                                    ])->visibleOn('edit'),
                                ])
                                ->columns(2)
                                ->columnSpan(2)
                                ->collapsible()
                                ->collapsed(fn(?Employee $record) => $record?->credential === null),
                        ])->columns(2),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\EmployeeResource\RelationManagers\VacacionesRelationManager::class,
            \App\Filament\Resources\EmployeeResource\RelationManagers\DocumentosRelationManager::class,
            \App\Filament\Resources\EmployeeResource\RelationManagers\RenovacionesContratoRelationManager::class,
            \App\Filament\Resources\EmployeeResource\RelationManagers\EscalasComisionRelationManager::class,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Foto de perfil miniatura
                Tables\Columns\ImageColumn::make('foto_perfil')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn() => 'https://ui-avatars.com/api/?background=1a7f4b&color=fff&size=40&name=?')
                    ->width(36)
                    ->height(36),

                Tables\Columns\TextColumn::make('nombre_completo')
                    ->label('Apellidos y Nombres')
                    ->getStateUsing(fn($record) => $record->nombre_completo)
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->where('apellidos', 'like', "%{$search}%")
                              ->orWhere('nombres', 'like', "%{$search}%");
                    })
                    ->sortable(['apellidos']),

                Tables\Columns\TextColumn::make('dni')
                    ->label('DNI')
                    ->searchable(),

                Tables\Columns\TextColumn::make('cargo')
                    ->label('Cargo')
                    ->searchable(),
		
		Tables\Columns\TextColumn::make('sueldo_base')
    ->label('Sueldo Base')
    ->money('PEN')
    ->sortable()
    ->toggleable(isToggledHiddenByDefault: true),

Tables\Columns\TextColumn::make('cuspp')
    ->label('CUSPP')
    ->placeholder(fn ($record) => str_starts_with((string) $record->sistema_pensiones, 'afp_') ? 'Falta' : '—')
    ->color(fn ($state, $record) => blank($state) && str_starts_with((string) $record->sistema_pensiones, 'afp_') ? 'danger' : null)
    ->searchable()
    ->toggleable(),

Tables\Columns\TextColumn::make('sistema_pensiones')
    ->label('Pensiones')
    ->formatStateUsing(fn($state) => match($state) {
        'onp'           => 'ONP',
        'afp_prima'     => 'AFP Prima',
        'afp_integra'   => 'AFP Integra',
        'afp_habitat'   => 'AFP Habitat',
        'afp_profuturo' => 'AFP Profuturo',
        default         => $state,
    })
    ->badge()
    ->color('info')
    ->toggleable(isToggledHiddenByDefault: true),
		
                Tables\Columns\TextColumn::make('location.nombre')
                    ->label('Sede')
                    ->sortable(),

                Tables\Columns\TextColumn::make('department.nombre')
                    ->label('Área')
                    ->sortable(),

                Tables\Columns\TextColumn::make('schedule.nombre')
                    ->label('Horario')
                    ->sortable(),

                // Badge de acceso remoto
                Tables\Columns\BadgeColumn::make('acceso_remoto')
                    ->label('App Móvil')
                    ->getStateUsing(function (Employee $record): string {
                        if (! $record->credential)          return 'Sin acceso';
                        if (! $record->credential->active)  return 'Bloqueado';
                        return 'Activo';
                    })
                    ->colors([
                        'gray'    => 'Sin acceso',
                        'danger'  => 'Bloqueado',
                        'success' => 'Activo',
                    ]),

                Tables\Columns\IconColumn::make('exonerado_registro')
                    ->label('Exonerado')
                    ->boolean(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\Filter::make('afp_sin_cuspp')
                    ->label('AFP sin CUSPP')
                    ->toggle()
                    ->query(fn ($query) => $query->where('sistema_pensiones', 'like', 'afp_%')->where(fn ($q) => $q->whereNull('cuspp')->orWhere('cuspp', ''))),

                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Empresa')
                    ->relationship('company', 'razon_social'),

                Tables\Filters\SelectFilter::make('location_id')
                    ->label('Sede')
                    ->relationship('location', 'nombre'),

                Tables\Filters\SelectFilter::make('department_id')
                    ->label('Área')
                    ->relationship('department', 'nombre'),

                Tables\Filters\TernaryFilter::make('active')
                    ->label('Estado')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos'),

                Tables\Filters\TernaryFilter::make('exonerado_registro')
                    ->label('Exonerado SUNAFIL'),

                // Filtro por acceso remoto
                Tables\Filters\Filter::make('con_acceso_remoto')
                    ->label('Con acceso remoto')
                    ->query(fn(Builder $q) => $q->whereHas('credential', fn($q) => $q->where('active', true))),

                Tables\Filters\Filter::make('sin_foto_perfil')
                    ->label('Sin foto de perfil')
                    ->query(fn(Builder $q) => $q->whereNull('foto_perfil')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('certificado_trabajo')
                        ->label(fn ($record) => $record->fecha_cese ? 'Certificado de trabajo' : 'Constancia de trabajo')
                        ->icon('heroicon-o-document-text')
                        ->form([
                            Forms\Components\DatePicker::make('fecha')->label('Fecha de emisión')->native(false)->default(now())->required(),
                        ])
                        ->action(fn ($record, array $data) => redirect()->route('documentos.certificado-trabajo', ['employee' => $record->id, 'fecha' => $data['fecha']])),

                    Tables\Actions\Action::make('carta_retiro_cts')
                        ->label('Carta de retiro de CTS')
                        ->icon('heroicon-o-envelope')
                        ->visible(fn ($record) => (bool) $record->fecha_cese)
                        ->form([
                            Forms\Components\DatePicker::make('fecha')->label('Fecha de emisión')->native(false)
                                ->default(fn ($record) => $record->fecha_cese)->required()
                                ->helperText('Por defecto es la fecha de cese.'),
                        ])
                        ->action(fn ($record, array $data) => redirect()->route('documentos.carta-retiro-cts', ['employee' => $record->id, 'fecha' => $data['fecha']])),

                    Tables\Actions\Action::make('certificado_quinta')
                        ->label('Certificado de 5.ª categoría')
                        ->icon('heroicon-o-receipt-percent')
                        ->modalDescription('Los montos salen de lo que el sistema tiene del ejercicio (planillas, histórico, gratificaciones y liquidación). Revísalos y ajusta lo que haga falta antes de emitir.')
                        ->form(function ($record) {
                            $anio = $record->fecha_cese?->year ?? now()->year;
                            $p    = app(\App\Services\DocumentosLaboralesService::class)->quintaPrefill($record, $anio);

                            $monto = fn (string $k, string $label) => Forms\Components\TextInput::make($k)->label($label)->numeric()->prefix('S/')->default($p[$k])->required();

                            return [
                                Forms\Components\TextInput::make('anio')->label('Ejercicio')->numeric()->default($anio)->required()
                                    ->helperText('Si cambias el año, vuelve a abrir esta ventana para recalcular los montos sugeridos.'),
                                Forms\Components\Section::make('Rentas brutas')->columns(2)->schema([
                                    $monto('sueldos', 'Sueldos y salarios'),
                                    $monto('comisiones', 'Comisiones'),
                                    $monto('utilidades', 'Participación en las utilidades'),
                                    $monto('gratificacion', 'Gratificación'),
                                    $monto('bonificaciones', 'Bonificaciones extraordinarias'),
                                    $monto('remu_vacacional', 'Remuneración vacacional'),
                                    $monto('vac_truncas', 'Vacaciones truncas'),
                                    $monto('grat_trunca', 'Gratificación trunca'),
                                ]),
                                Forms\Components\Section::make('Deducción y retenciones')->columns(3)->schema([
                                    $monto('deduccion', '7 UIT (deducción)'),
                                    $monto('retenciones', 'Retenciones informadas'),
                                    $monto('devolucion', 'Devolución informada'),
                                ]),
                                Forms\Components\DatePicker::make('fecha')->label('Fecha de emisión')->native(false)->default(now())->required(),
                            ];
                        })
                        ->action(fn ($record, array $data) => redirect()->route('documentos.certificado-quinta', array_merge(['employee' => $record->id], $data))),
                ])->label('Documentos')->icon('heroicon-o-document-duplicate'),

                Tables\Actions\Action::make('resetear_dni')
                    ->label('Resetear al DNI')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Resetear contraseña')
                    ->modalDescription(fn($record) => "Se reseteará la contraseña de {$record->nombre_completo} a su DNI ({$record->dni}).")
                    ->action(function ($record): void {
                        EmployeeCredential::updateOrCreate(
                            ['employee_id' => $record->id],
                            ['password' => Hash::make($record->dni), 'active' => true]
                        );
                    })
                    ->successNotificationTitle('✅ Contraseña reseteada al DNI.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),

                // ── CAMBIAR HORARIO ───────────────────────────────────────────
                BulkAction::make('cambiar_horario')
                    ->label('Cambiar Horario')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Cambiar Horario en masa')
                    ->modalDescription('Se actualizará el horario de todos los trabajadores seleccionados.')
                    ->modalSubmitActionLabel('Sí, actualizar horarios')
                    ->form(function (Collection $records) {
                        $companyIds = $records->pluck('company_id')->unique()->filter();

                        return [
                            Select::make('schedule_id')
                                ->label('Nuevo Horario')
                                ->options(
                                    Schedule::query()
                                        ->when($companyIds->isNotEmpty(), fn($q) => $q->whereIn('company_id', $companyIds))
                                        ->orderBy('nombre')
                                        ->get()
                                        ->mapWithKeys(fn($s) => [
                                            $s->id => $s->nombre . ' (' . $s->hora_entrada . ' - ' . $s->hora_salida . ')'
                                        ])
                                )
                                ->required()
                                ->searchable()
                                ->helperText('Solo se muestran horarios de la(s) empresa(s) de los trabajadores seleccionados.'),
                        ];
                    })
                    ->action(function (Collection $records, array $data): void {
                        Employee::whereIn('id', $records->pluck('id'))
                            ->update(['schedule_id' => $data['schedule_id']]);
                    })
                    ->deselectRecordsAfterCompletion()
                    ->successNotificationTitle('✅ Horarios actualizados correctamente'),

                // ── ACTIVAR ACCESO REMOTO MASIVO ──────────────────────────────
                BulkAction::make('activar_acceso_remoto')
                    ->label('Activar Acceso Remoto')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Activar acceso remoto en masa')
                    ->modalDescription('Se creará o activará el acceso a la app móvil para los trabajadores seleccionados. Se generará una contraseña temporal igual al DNI de cada trabajador.')
                    ->modalSubmitActionLabel('Sí, activar')
                    ->action(function (Collection $records): void {
                        foreach ($records as $employee) {
                            EmployeeCredential::updateOrCreate(
                                ['employee_id' => $employee->id],
                                [
                                    'password' => Hash::make($employee->dni), // contraseña temporal = DNI
                                    'active'   => true,
                                ]
                            );
                        }
                    })
                    ->deselectRecordsAfterCompletion()
                    ->successNotificationTitle('✅ Acceso remoto activado. Contraseña temporal: DNI del trabajador.'),
            ])
            ->defaultSort('apellidos');
    }

    // ── Guardar credenciales al salvar el formulario ──────────────────────────

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
\Log::info('handleRecordUpdate data:', $data);
        // Extraemos los datos de credencial antes de guardar Employee
        $passwordNueva    = $data['credential']['password_nueva'] ?? null;
        $credentialActive = $data['credential']['active'] ?? false;
        $resetearDni      = $data['credential']['resetear_dni'] ?? false;
        unset($data['credential']);

        $record->fill($data)->save();

        // Solo tocamos credencial si hay algo que hacer
        if ($passwordNueva || $resetearDni || $credentialActive || $record->credential) {
            $credData = ['active' => $credentialActive];

            if ($passwordNueva) {
                $credData['password'] = Hash::make($passwordNueva);
            } elseif ($resetearDni || !$record->credential) {
                $credData['password'] = Hash::make($record->dni);
            }

            EmployeeCredential::updateOrCreate(
                ['employee_id' => $record->id],
                $credData
            );
        }

        return $record;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit'   => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('credential');
        $companyId = \App\Helpers\CompanyContext::get();
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        return $query;
    }
}
