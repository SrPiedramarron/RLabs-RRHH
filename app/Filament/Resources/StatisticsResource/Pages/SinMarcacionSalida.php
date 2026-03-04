<?php

namespace App\Filament\Resources\StatisticsResource\Pages;

use App\Filament\Resources\StatisticsResource;
use App\Models\AttendanceRecord;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class SinMarcacionSalida extends ListRecords
{
    protected static string $resource = StatisticsResource::class;

    protected function getTableQuery(): Builder
    {
        $user = Auth::user();

        return AttendanceRecord::query()
            ->whereNotNull('hora_entrada')
            ->whereNull('hora_salida')
            ->where('estado', '!=', 'ausente')
            ->with(['employee', 'location'])
            ->when($user->company_id, function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.nombre_completo')
                    ->label('Empleado')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('location.nombre')
                    ->label('Sede'),

                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('hora_entrada')
                    ->label('Hora entrada')
                    ->formatStateUsing(fn($state) => $state ? \Carbon\Carbon::parse($state)->format('H:i') : '—'),

                Tables\Columns\BadgeColumn::make('sin_salida')
                    ->label('Marcó salida')
                    ->getStateUsing(fn($record) => $record->hora_salida ? 'Sí' : 'No')
                    ->colors([
                        'success' => 'Sí',
                        'danger'  => 'No',
                    ]),
            ])
            ->filters([
                Tables\Filters\Filter::make('periodo')
                    ->form([
                        Forms\Components\DatePicker::make('desde')
                            ->label('Desde')
                            ->default(now()->subDays(30)),
                        Forms\Components\DatePicker::make('hasta')
                            ->label('Hasta')
                            ->default(now()),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['desde'], fn($q) => $q->whereDate('fecha', '>=', $data['desde']))
                            ->when($data['hasta'], fn($q) => $q->whereDate('fecha', '<=', $data['hasta']));
                    }),
            ])
            ->defaultSort('fecha', 'desc');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('volver')
                ->label('← Ranking')
                ->url(StatisticsResource::getUrl('index'))
                ->color('gray'),

            Actions\Action::make('exportar')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $user = Auth::user();
                    $records = AttendanceRecord::query()
                        ->whereNotNull('hora_entrada')
                        ->whereNull('hora_salida')
                        ->where('estado', '!=', 'ausente')
                        ->with(['employee', 'location'])
                        ->when($user->company_id, fn($q) => $q->where('company_id', $user->company_id))
                        ->orderBy('fecha', 'desc')
                        ->get();
                    return Excel::download(
                        new \App\Exports\SinMarcacionSalidaExport($records),
                        'sin_marcacion_salida_' . now()->format('Y-m-d') . '.xlsx'
                    );
                }),
        ];
    }

    public function getTitle(): string
    {
        return 'Empleados sin Marcación de Salida';
    }
}
