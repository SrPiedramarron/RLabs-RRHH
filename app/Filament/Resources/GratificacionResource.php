<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GratificacionResource\Pages;
use App\Filament\Traits\HasCompanyScope;
use App\Models\Gratificacion;
use App\Services\PlanillaService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GratificacionResource extends Resource
{
    use HasCompanyScope;

    protected static ?string $model            = Gratificacion::class;
    protected static ?string $slug             = 'gratificaciones';
    protected static ?string $navigationIcon   = 'heroicon-o-gift';
    protected static ?string $navigationLabel  = 'Gratificaciones';
    protected static ?string $navigationGroup  = 'Planilla';
    protected static ?int    $navigationSort   = 15;
    protected static ?string $modelLabel       = 'Gratificación';
    protected static ?string $pluralModelLabel = 'Gratificaciones';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre_completo')
                    ->label('Trabajador')
                    ->getStateUsing(fn ($record) => $record->apellidos . ', ' . $record->nombres)
                    ->searchable(query: fn ($query, $search) => $query->where('apellidos', 'like', "%$search%")->orWhere('nombres', 'like', "%$search%"))
                    ->sortable(['apellidos']),

                Tables\Columns\TextColumn::make('mes_nombre')
                    ->label('Periodo')
                    ->sortable(['periodo'])
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('meses_computables')
                    ->label('Meses')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => "{$state}/6")
                    ->color(fn ($state) => $state < 6 ? 'warning' : 'gray'),

                Tables\Columns\TextColumn::make('remuneracion_computable')
                    ->label('Remun. computable')
                    ->money('PEN')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('monto_gratificacion')
                    ->label('Gratificación')
                    ->money('PEN')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('bonificacion_extraordinaria')
                    ->label('Bonif. 9% (Ley 29351)')
                    ->money('PEN')
                    ->alignEnd()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('monto_total')
                    ->label('Total a pagar')
                    ->money('PEN')
                    ->alignEnd()
                    ->weight('bold')
                    ->color('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('periodo')
                    ->label('Periodo')
                    ->options(function () {
                        if (!Gratificacion::exists()) return [];
                        return Gratificacion::distinct()
                            ->orderByDesc('periodo')
                            ->pluck('mes_nombre', 'periodo')
                            ->toArray();
                    }),

                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Empresa')
                    ->relationship('company', 'razon_social'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('calcular_gratificacion')
                    ->label('Calcular gratificación')
                    ->icon('heroicon-o-calculator')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('tipo')
                            ->label('Gratificación')
                            ->options([
                                'julio' => 'Julio (Fiestas Patrias) — semestre Ene-Jun',
                                'diciembre' => 'Diciembre (Navidad) — semestre Jul-Dic',
                            ])
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('anio')
                            ->label('Año')
                            ->options(function () {
                                $actual = (int) now()->year;
                                return [$actual - 1 => (string) ($actual - 1), $actual => (string) $actual, $actual + 1 => (string) ($actual + 1)];
                            })
                            ->default((int) now()->year)
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->relationship('company', 'razon_social')
                            ->required()
                            ->searchable(),

                        Forms\Components\Placeholder::make('aviso')
                            ->label('')
                            ->content('Remuneración computable = sueldo base + asignación familiar + promedio de comisiones de los últimos 6 meses (solo si tuvo en al menos 3) + promedio de horas extra de los últimos 6 meses (solo si tuvo en al menos 3). Si no completó el semestre, se prorratea. Incluye la bonificación extraordinaria del 9% (Ley 29351).'),
                    ])
                    ->action(function (array $data) {
                        try {
                            $resultado = app(PlanillaService::class)->calcularPeriodoGratificacion(
                                $data['company_id'],
                                $data['tipo'],
                                (int) $data['anio'],
                            );

                            Notification::make()
                                ->title("Gratificación calculada: {$resultado->count()} trabajadores")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error al calcular')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('periodo', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGratificaciones::route('/'),
        ];
    }
}
