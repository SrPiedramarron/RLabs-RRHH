<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopTardinessWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Top Tardanzas del Mes';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Employee::query()
                    ->withCount([
                        'attendanceRecords as total_tardanzas' => fn($q) =>
                            $q->whereMonth('fecha', now()->month)
                              ->whereYear('fecha', now()->year)
                              ->where('estado', 'tarde'),
                    ])
                    ->withSum([
                        'attendanceRecords as total_minutos' => fn($q) =>
                            $q->whereMonth('fecha', now()->month)
                              ->whereYear('fecha', now()->year)
                              ->where('estado', 'tarde'),
                    ], 'minutos_tarde')
                    ->having('total_tardanzas', '>', 0)
                    ->orderByDesc('total_tardanzas')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('nombre_completo')
                    ->label('Empleado')
                    ->getStateUsing(fn($record) => $record->nombre_completo),

                Tables\Columns\TextColumn::make('dni')
                    ->label('DNI'),

                Tables\Columns\TextColumn::make('total_tardanzas')
                    ->label('Tardanzas este mes')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_minutos')
                    ->label('Total Minutos')
                    ->sortable(),
            ]);
    }
}