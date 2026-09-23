<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CtsResource\Pages;
use App\Filament\Traits\HasCompanyScope;
use App\Models\CtsDeposito;
use App\Services\PlanillaService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CtsResource extends Resource
{
    use HasCompanyScope;

    protected static ?string $model            = CtsDeposito::class;
    protected static ?string $slug             = 'cts';
    protected static ?string $navigationIcon   = 'heroicon-o-building-library';
    protected static ?string $navigationLabel  = 'CTS';
    protected static ?string $navigationGroup  = 'Beneficios Sociales';
    protected static ?int    $navigationSort   = 20;
    protected static ?string $modelLabel       = 'Depósito CTS';
    protected static ?string $pluralModelLabel = 'CTS';

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

                Tables\Columns\TextColumn::make('sexto_gratificacion')
                    ->label('1/6 gratificación')
                    ->money('PEN')
                    ->alignEnd()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('remuneracion_computable')
                    ->label('Remun. computable')
                    ->money('PEN')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('monto_cts')
                    ->label('Monto CTS')
                    ->money('PEN')
                    ->alignEnd()
                    ->weight('bold')
                    ->color('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('periodo')
                    ->label('Periodo')
                    ->options(function () {
                        if (!CtsDeposito::exists()) return [];
                        return CtsDeposito::distinct()
                            ->orderByDesc('periodo')
                            ->pluck('mes_nombre', 'periodo')
                            ->toArray();
                    }),

                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Empresa')
                    ->relationship('company', 'razon_social'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('calcular_cts')
                    ->label('Calcular CTS')
                    ->icon('heroicon-o-calculator')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('tipo')
                            ->label('Depósito')
                            ->options([
                                'mayo' => 'Mayo — semestre Noviembre-Abril',
                                'noviembre' => 'Noviembre — semestre Mayo-Octubre',
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
                            ->content('Remuneración computable = sueldo base + asignación familiar + promedio de comisiones/horas extra del semestre (solo si tuvo en >= 3 de 6 meses) + 1/6 de la gratificación percibida en ese semestre. Monto = remuneración computable ÷ 12 × meses trabajados del semestre. Si la gratificación del semestre aún no se calculó, el 1/6 queda en 0 — calcúlala primero en Gratificaciones.'),
                    ])
                    ->action(function (array $data) {
                        try {
                            $resultado = app(PlanillaService::class)->calcularPeriodoCts(
                                $data['company_id'],
                                $data['tipo'],
                                (int) $data['anio'],
                            );

                            Notification::make()
                                ->title("CTS calculada: {$resultado->count()} trabajadores")
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
            'index' => Pages\ListCtsDepositos::route('/'),
        ];
    }
}
