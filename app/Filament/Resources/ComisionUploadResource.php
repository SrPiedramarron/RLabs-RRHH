<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComisionUploadResource\Pages;
use App\Filament\Traits\HasCompanyScope;
use App\Models\ComisionDetalle;
use App\Models\ComisionUpload;
use App\Services\ComisionesService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ComisionUploadResource extends Resource
{
    use HasCompanyScope;

    protected static ?string $model = ComisionUpload::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Comisiones';
    protected static ?string $navigationGroup = 'Planilla';
    protected static ?int $navigationSort = 10;
    protected static ?string $modelLabel = 'Carga de comisiones';
    protected static ?string $pluralModelLabel = 'Cargas de comisiones';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Empresa y Período')
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->relationship('company', 'razon_social')
                            ->required()
                            ->searchable()
                            ->default(fn () => \App\Helpers\CompanyContext::get()),

                        Forms\Components\Select::make('periodo')
                            ->label('Período')
                            ->options(static::generarOpcionesPeriodo())
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('Solo se puede subir una vez por período. Para corregir, elimine el registro anterior.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Archivos Excel')
                    ->schema([
                        Forms\Components\FileUpload::make('archivo_cobranzas')
                            ->label('Excel de Cobranzas')
                            ->helperText('Archivo "OFI_Detalles_de_cobranzas_XXXX.xlsx"')
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                            ->directory('comisiones/cobranzas')
                            ->required(),

                        Forms\Components\FileUpload::make('archivo_comisiones')
                            ->label('Excel de Comisiones')
                            ->helperText('Archivo "OFI_COMISIONES_XXXX.xlsx"')
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                            ->directory('comisiones/comisiones')
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('mes_nombre')
                    ->label('Período')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'warning' => 'procesando',
                        'success' => 'completado',
                        'danger'  => 'error',
			'warning' => 'huerfana', 
                    ]),

                Tables\Columns\TextColumn::make('total_facturas')
                    ->label('Facturas')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_cobradas')
                    ->label('Cobradas')
                    ->alignCenter()
                    ->color('success'),

                Tables\Columns\TextColumn::make('total_pendientes')
                    ->label('Pendientes')
                    ->alignCenter()
                    ->color('warning'),
		Tables\Columns\TextColumn::make('huerfanas_count')
    ->label('? Sin comisiones')
    ->getStateUsing(fn ($record) => 
        \App\Models\ComisionDetalle::where('comision_upload_id', $record->id)
            ->where('estado', 'huerfana')->count()
    )
    ->color('warning')
    ->alignCenter()
    ->placeholder('�'),

                Tables\Columns\TextColumn::make('total_base_cobrada')
                    ->label('Base cobrada (S/)')
                    ->money('PEN')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_comision')
                    ->label('Comisión total (S/)')
                    ->money('PEN')
                    ->alignEnd()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Procesado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_detalle')
                    ->label('Ver detalle')
                    ->icon('heroicon-o-table-cells')
                    ->url(fn (ComisionUpload $record) => static::getUrl('detalle', ['record' => $record]))
                    ->visible(fn (ComisionUpload $record) => $record->estado === 'completado'),

                Tables\Actions\Action::make('reprocesar')
                    ->label('Reprocesar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (ComisionUpload $record) => static::reprocesar($record))
                    ->visible(fn (ComisionUpload $record) => $record->estado === 'error'),

                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('periodo', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'   => Pages\ListComisionUploads::route('/'),
            'create'  => Pages\CreateComisionUpload::route('/create'),
            'detalle' => Pages\DetalleComisionUpload::route('/{record}/detalle'),
        ];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private static function generarOpcionesPeriodo(): array
    {
        $meses = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
            '04' => 'Abril', '05' => 'Mayo',    '06' => 'Junio',
            '07' => 'Julio', '08' => 'Agosto',  '09' => 'Septiembre',
            '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
        ];

        $options = [];
        $year    = now()->year;

        // Ofrecer 12 meses hacia atrás y 2 hacia adelante
        for ($i = -12; $i <= 2; $i++) {
            $date  = now()->addMonths($i);
            $key   = $date->format('Y-m');
            $mes   = $meses[$date->format('m')];
            $options[$key] = "$mes {$date->year}";
        }

        return array_reverse($options, true);
    }

    public static function reprocesar(ComisionUpload $record): void
    {
        $record->detalles()->delete();

        try {
            app(ComisionesService::class)->procesar(
                $record,
                Storage::disk('public')->path($record->archivo_cobranzas),
                Storage::disk('public')->path($record->archivo_comisiones),
            );

            $record->refresh();

            if ($record->vendedores_sin_match) {
                Notification::make()
                    ->title('Reprocesado con advertencias')
                    ->body('No se encontró empleado para: ' . implode(', ', $record->vendedores_sin_match) . '. Sus comisiones no se sumarán a planilla hasta que el nombre coincida exactamente con el del empleado.')
                    ->warning()
                    ->persistent()
                    ->send();
            } else {
                Notification::make()
                    ->title('Reprocesado correctamente')
                    ->success()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error al reprocesar')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
