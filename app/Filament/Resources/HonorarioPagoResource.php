<?php

namespace App\Filament\Resources;

use App\Exports\HonorariosPagoExport;
use App\Filament\Resources\HonorarioPagoResource\Pages;
use App\Filament\Traits\HasCompanyScope;
use App\Models\HonorarioPago;
use App\Services\HonorarioService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

class HonorarioPagoResource extends Resource
{
    use HasCompanyScope;

    protected static ?string $model            = HonorarioPago::class;
    protected static ?string $slug             = 'honorarios';
    protected static ?string $navigationIcon   = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel  = 'Honorarios (locadores)';
    protected static ?string $navigationGroup  = 'Planilla';
    protected static ?int    $navigationSort   = 30;
    protected static ?string $modelLabel       = 'Pago de honorarios';
    protected static ?string $pluralModelLabel = 'Honorarios (locadores)';

    public static function canCreate(): bool
    {
        return false;
    }

    private static function opcionesPeriodo(): array
    {
        $opts = [];
        for ($i = 0; $i < 6; $i++) {
            $d = now()->subMonths($i);
            $opts[$d->format('Y-m')] = ucfirst($d->locale('es')->isoFormat('MMMM YYYY'));
        }

        return $opts;
    }

    private static function formularioQuincena(): array
    {
        return [
            Forms\Components\Select::make('company_id')->label('Empresa')->relationship('company', 'razon_social')->required()->searchable(),
            Forms\Components\Select::make('periodo')->label('Mes')->options(fn () => static::opcionesPeriodo())->default(now()->format('Y-m'))->required()->native(false),
            Forms\Components\Select::make('quincena')->label('Quincena')->options([1 => '1.ª quincena', 2 => '2.ª quincena'])->required()->native(false),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.apellidos')
                    ->label('Locador')
                    ->getStateUsing(fn ($record) => $record->employee->apellidos . ', ' . $record->employee->nombres)
                    ->searchable(query: fn ($q, $s) => $q->whereHas('employee', fn ($e) => $e->where('apellidos', 'like', "%$s%")->orWhere('nombres', 'like', "%$s%"))),
                Tables\Columns\TextColumn::make('periodo')->label('Mes')->sortable(),
                Tables\Columns\TextColumn::make('quincena')->label('Quincena')->formatStateUsing(fn ($state) => $state . '.ª')->alignCenter(),
                Tables\Columns\TextColumn::make('honorario_bruto')->label('Honorario')->money('PEN'),
                Tables\Columns\TextColumn::make('retencion_4ta')->label('Retención 4.ª')->money('PEN')
                    ->description(fn ($record) => $record->suspension_4ta ? 'Con suspensión' : null),
                Tables\Columns\TextColumn::make('neto_pagar')->label('Neto a pagar')->money('PEN')->weight('bold'),
                Tables\Columns\TextColumn::make('recibo_numero')->label('Recibo N.º')->placeholder('Falta')->badge()
                    ->color(fn ($state) => $state ? 'success' : 'warning'),
                Tables\Columns\IconColumn::make('pagado')->label('Pagado')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('periodo')->label('Mes')->options(fn () => static::opcionesPeriodo()),
                Tables\Filters\SelectFilter::make('quincena')->label('Quincena')->options([1 => '1.ª', 2 => '2.ª']),
                Tables\Filters\TernaryFilter::make('pagado')->label('Pagado'),
                Tables\Filters\Filter::make('sin_recibo')->label('Sin recibo')->toggle()->query(fn ($q) => $q->whereNull('recibo_numero')),
            ])
            ->headerActions([
                Tables\Actions\Action::make('calcular')
                    ->label('Calcular quincena')
                    ->icon('heroicon-o-calculator')
                    ->color('primary')
                    ->form(array_merge(static::formularioQuincena(), [
                        Forms\Components\Placeholder::make('aviso')->label('')
                            ->content('Cada locador activo con honorario mensual recibe la mitad del honorario por quincena. Se retiene el 8% si el recibo supera el umbral y no tiene suspensión de 4.ª categoría. Los datos de recibo y de pago ya registrados no se pierden al recalcular.'),
                    ]))
                    ->action(function (array $data) {
                        try {
                            $pagos = app(HonorarioService::class)->calcularQuincena((int) $data['company_id'], $data['periodo'], (int) $data['quincena']);

                            Notification::make()->title("Quincena calculada: {$pagos->count()} locador(es)")->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('No se pudo calcular')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('exportar')
                    ->label('Exportar pago (Excel)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->form(static::formularioQuincena())
                    ->action(function (array $data) {
                        $pagos = HonorarioPago::with('employee')
                            ->where('company_id', $data['company_id'])
                            ->where('periodo', $data['periodo'])
                            ->where('quincena', $data['quincena'])
                            ->get()
                            ->sortBy(fn ($p) => $p->employee->apellidos)
                            ->values();

                        if ($pagos->isEmpty()) {
                            Notification::make()->title('No hay pagos calculados para esa quincena')->warning()->send();

                            return null;
                        }

                        $titulo = 'Honorarios ' . $data['periodo'] . ' Q' . $data['quincena'];

                        return Excel::download(new HonorariosPagoExport($pagos, $titulo), 'honorarios_' . $data['periodo'] . '_q' . $data['quincena'] . '.xlsx');
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('recibo')
                    ->label('Recibo')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->form([
                        Forms\Components\TextInput::make('recibo_numero')->label('N.º del recibo por honorarios')->maxLength(30),
                        Forms\Components\DatePicker::make('recibo_fecha')->label('Fecha del recibo')->native(false),
                    ])
                    ->fillForm(fn ($record) => ['recibo_numero' => $record->recibo_numero, 'recibo_fecha' => $record->recibo_fecha])
                    ->action(fn ($record, array $data) => $record->update($data)),

                Tables\Actions\Action::make('pagar')
                    ->label('Marcar pagado')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => ! $record->pagado)
                    ->action(fn ($record) => $record->update(['pagado' => true, 'pagado_at' => now()])),
            ])
            ->bulkActions([])
            ->defaultSort('periodo', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListHonorarioPagos::route('/')];
    }
}
