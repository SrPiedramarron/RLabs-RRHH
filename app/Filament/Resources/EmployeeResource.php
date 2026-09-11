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

                            Forms\Components\Select::make('schedule_id')
                                ->label('Horario Principal')
                                ->relationship('schedule', 'nombre')
                                ->required()
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

                            Forms\Components\TextInput::make('cargo')
                                ->label('Cargo')
                                ->maxLength(100),

                            Forms\Components\DatePicker::make('fecha_ingreso')
                                ->label('Fecha de Ingreso')
                                ->required()
                                ->displayFormat('d/m/Y'),

                            Forms\Components\DatePicker::make('fecha_cese')
                                ->label('Fecha de Cese')
                                ->displayFormat('d/m/Y')
                                ->nullable(),

                            Forms\Components\Toggle::make('active')
                                ->label('Activo')
                                ->default(true),
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

                                    Forms\Components\TextInput::make('credential.password_nueva')
                                        ->label('Contraseña')
                                        ->helperText('Déjalo en blanco para no cambiar la contraseña actual. Mínimo 6 caracteres.')
                                        ->password()
                                        ->revealable()
                                        ->minLength(6)
                                        ->dehydrated(false)
                                        ->nullable(),

                                    Forms\Components\Toggle::make('credential.resetear_dni')
                                        ->label('Resetear contraseña al DNI')
                                        ->helperText('Marca para restablecer la contraseña al DNI del trabajador.')
                                        ->default(false)
                                        ->visibleOn('edit'),

                                    Forms\Components\Toggle::make('credential.active')
                                        ->label('Acceso activo')
                                        ->helperText('Desactiva para bloquear el acceso sin eliminar la contraseña.')
                                        ->default(true)
                                        ->visibleOn('edit'),
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
