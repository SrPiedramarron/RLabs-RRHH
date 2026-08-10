<?php

namespace App\Filament\Resources\ComisionUploadResource\Pages;

use App\Filament\Resources\ComisionUploadResource;
use App\Models\ComisionDetalle;
use App\Models\ComisionUpload;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DetalleComisionUpload extends Page implements HasTable
{
    use InteractsWithTable;
    use InteractsWithRecord;

    protected static string $resource = ComisionUploadResource::class;
    protected static string $view     = 'filament.resources.comision-upload.detalle';

    // public ComisionUpload $record;

     public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        \Log::info('mount ejecutado', ['id' => $this->record->id]);
    }
    

    // ─── Resumen por vendedor para las tarjetas superiores ────────────────────

    public function getResumenVendedores(): \Illuminate\Support\Collection
    {
        return ComisionDetalle::where('comision_upload_id', $this->record->id)
            ->selectRaw('
                vendedor,
                COUNT(*) as total,
                SUM(estado = "cobrada") as cobradas,
                SUM(estado = "pendiente") as pendientes,
                COALESCE(SUM(base_comision_cobrada), 0) as base_cobrada,
                COALESCE(SUM(comision_calculada), 0) as comision
            ')
            ->groupBy('vendedor')
            ->orderBy('vendedor')
            ->get();
    }

    // ─── Tabla de detalle ─────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ComisionDetalle::where('comision_upload_id', $this->record->id)
            )
            ->columns([
                Tables\Columns\TextColumn::make('vendedor')
                    ->label('Vendedor')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('numdoc')
                    ->label('Comprobante')
                    ->searchable(),

                Tables\Columns\TextColumn::make('razon_social')
                    ->label('Cliente')
                    ->searchable()
                    ->limit(35)
                    ->tooltip(fn ($record) => $record->razon_social),

                Tables\Columns\TextColumn::make('fecha_emision')
                    ->label('Emisión')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('condicion')
                    ->label('Condición')
                    ->badge()
                    ->color(fn (string $state): string => str_contains(strtolower($state), 'contado') ? 'success' : 'info'),

                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'success' => 'cobrada',
                        'warning' => 'pendiente',
                        'danger'  => 'anulada',
                    ]),

                Tables\Columns\TextColumn::make('fecha_pago')
                    ->label('F. Pago')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('base_comision_cobrada')
                    ->label('Base cobrada (S/)')
                    ->money('PEN')
                    ->alignEnd()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('comision_calculada')
                    ->label('Comisión 1.5% (S/)')
                    ->money('PEN')
                    ->alignEnd()
                    ->weight('bold')
                    ->color('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('vendedor')
                    ->options(
                        ComisionDetalle::where('comision_upload_id', $this->record->id)
                            ->distinct()
                            ->pluck('vendedor', 'vendedor')
                            ->toArray()
                    )
                    ->label('Filtrar por vendedor'),

                Tables\Filters\SelectFilter::make('estado')
                    ->options([
                        'cobrada'   => 'Cobradas',
                        'pendiente' => 'Pendientes',
                        'anulada'   => 'Anuladas',
                    ])
                    ->label('Estado'),
            ])
            ->defaultSort('vendedor')
            ->striped()
            ->paginated([25, 50, 100]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportar')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(route('comisiones.exportar', $this->record->id))
                ->openUrlInNewTab(),

            Action::make('volver')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->url(ComisionUploadResource::getUrl('index'))
                ->color('gray'),
        ];
    }

    public function getTitle(): string
    {
        return 'Detalle de comisiones — ' . $this->record->mes_nombre;
    }
    public function getHuerfanas(): \Illuminate\Support\Collection
{
    return ComisionDetalle::where('comision_upload_id', $this->record->id)
        ->where('estado', 'huerfana')
        ->get();
}

}
