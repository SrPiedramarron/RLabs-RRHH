<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UtilidadResource\Pages;
use App\Filament\Traits\HasCompanyScope;
use App\Models\Utilidad;
use App\Services\PlanillaService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UtilidadResource extends Resource
{
    use HasCompanyScope;

    protected static ?string $model            = Utilidad::class;
    protected static ?string $slug             = 'utilidades';
    protected static ?string $navigationIcon   = 'heroicon-o-chart-pie';
    protected static ?string $navigationLabel  = 'Utilidades';
    protected static ?string $navigationGroup  = 'Planilla';
    protected static ?int    $navigationSort   = 40;
    protected static ?string $modelLabel       = 'Utilidad';
    protected static ?string $pluralModelLabel = 'Utilidades';

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

                Tables\Columns\TextColumn::make('anio_ejercicio')
                    ->label('Ejercicio')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('dias_trabajados_anual')
                    ->label('Días trabajados')
                    ->alignCenter()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('remuneracion_anual')
                    ->label('Remun. anual')
                    ->money('PEN')
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('monto_por_dias')
                    ->label('50% por días')
                    ->money('PEN')
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('monto_por_remuneracion')
                    ->label('50% por remun.')
                    ->money('PEN')
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('tope_aplicado')
                    ->label('Tope 18 remun.')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('monto_pagado')
                    ->label('Monto a pagar')
                    ->money('PEN')
                    ->alignEnd()
                    ->weight('bold')
                    ->color('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('anio_ejercicio')
                    ->label('Ejercicio')
                    ->options(function () {
                        if (!Utilidad::exists()) return [];
                        return Utilidad::distinct()->orderByDesc('anio_ejercicio')->pluck('anio_ejercicio', 'anio_ejercicio')->toArray();
                    }),

                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Empresa')
                    ->relationship('company', 'razon_social'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('calcular_utilidades')
                    ->label('Calcular reparto de utilidades')
                    ->icon('heroicon-o-calculator')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->relationship('company', 'razon_social')
                            ->required()
                            ->searchable(),

                        Forms\Components\Select::make('anio_ejercicio')
                            ->label('Año que se reparte')
                            ->options(function () {
                                $actual = (int) now()->year;
                                return [$actual - 2 => (string) ($actual - 2), $actual - 1 => (string) ($actual - 1), $actual => (string) $actual];
                            })
                            ->default((int) now()->year - 1)
                            ->required()
                            ->native(false)
                            ->helperText('El año fiscal cuyas utilidades se están repartiendo — normalmente el año anterior.'),

                        Forms\Components\TextInput::make('monto_total')
                            ->label('Monto total a repartir (S/)')
                            ->numeric()
                            ->prefix('S/')
                            ->required()
                            ->helperText('El monto que entrega contabilidad, ya calculado según el % legal de la actividad de la empresa (5/8/10%) sobre la renta neta del ejercicio. Este sistema NO calcula ese %, solo hace el reparto entre trabajadores.'),

                        Forms\Components\DatePicker::make('fecha_pago')
                            ->label('Mes de pago')
                            ->displayFormat('m/Y')
                            ->required()
                            ->helperText('Normalmente dentro de los 30 días de presentada la declaración anual (marzo-abril).'),

                        Forms\Components\Placeholder::make('aviso')
                            ->label('')
                            ->content('Reparte 50% proporcional a los días trabajados en el año y 50% proporcional a la remuneración percibida. Cada trabajador tiene un tope de 18 remuneraciones mensuales — el exceso sobre el tope no se redistribuye (por ley va a un fondo estatal). Requiere que la Liquidación de Planilla de los 12 meses de ese año ya esté calculada.'),
                    ])
                    ->action(function (array $data) {
                        try {
                            $periodoPago = \Illuminate\Support\Carbon::parse($data['fecha_pago'])->format('Y-m');

                            $resultado = app(PlanillaService::class)->calcularPeriodoUtilidades(
                                $data['company_id'],
                                (int) $data['anio_ejercicio'],
                                (float) $data['monto_total'],
                                $periodoPago,
                            );

                            Notification::make()
                                ->title("Utilidades repartidas: {$resultado->count()} trabajadores")
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
            ->defaultSort('anio_ejercicio', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUtilidades::route('/'),
        ];
    }
}
