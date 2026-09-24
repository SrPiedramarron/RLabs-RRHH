<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractRenewalResource\Pages;
use App\Helpers\CompanyContext;
use App\Models\ContractRenewal;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContractRenewalResource extends Resource
{
    // No usa HasCompanyScope porque este modelo no tiene columna company_id
    // propia (la empresa vive en employee_id->company_id) — se filtra abajo.

    protected static ?string $model            = ContractRenewal::class;
    protected static ?string $navigationIcon   = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel  = 'Renovaciones de Contrato';
    protected static ?string $navigationGroup  = 'Reportes';
    protected static ?int    $navigationSort   = 3;
    protected static ?string $modelLabel       = 'Renovación';
    protected static ?string $pluralModelLabel = 'Renovaciones de Contrato';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['employee', 'renovadoPor']);

        if ($companyId = CompanyContext::get()) {
            $query->whereHas('employee', fn ($q) => $q->where('company_id', $companyId));
        }

        return $query;
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.nombre_completo')
                    ->label('Trabajador')
                    ->searchable(query: fn (Builder $q, string $search) => $q->whereHas('employee', fn ($e) => $e->where('apellidos', 'like', "%$search%")->orWhere('nombres', 'like', "%$search%")))
                    ->sortable(),

                Tables\Columns\TextColumn::make('employee.dni')
                    ->label('DNI'),

                Tables\Columns\TextColumn::make('employee.cargo')
                    ->label('Cargo'),

                Tables\Columns\TextColumn::make('employee.tipo_contrato_label')
                    ->label('Tipo de Contrato')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('numero_renovacion')
                    ->label('N° Renovación')
                    ->formatStateUsing(fn ($state) => "N° {$state}")
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('fecha_fin_anterior')
                    ->label('Fin anterior')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('fecha_fin_nueva')
                    ->label('Fin nuevo')
                    ->date('d/m/Y')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('observacion')
                    ->label('Observación')
                    ->limit(40)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('renovadoPor.name')
                    ->label('Renovado por')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('renovado_at')
                    ->label('Fecha de renovación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('periodo')
                    ->form([
                        Forms\Components\DatePicker::make('desde')->label('Desde'),
                        Forms\Components\DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['desde'], fn ($q) => $q->whereDate('renovado_at', '>=', $data['desde']))
                            ->when($data['hasta'], fn ($q) => $q->whereDate('renovado_at', '<=', $data['hasta']));
                    }),

                Tables\Filters\SelectFilter::make('company')
                    ->label('Empresa')
                    ->options(fn () => \App\Models\Company::pluck('razon_social', 'id'))
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $companyId) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $companyId))
                    )),

                Tables\Filters\SelectFilter::make('tipo_contrato')
                    ->label('Tipo de Contrato')
                    ->options([
                        'indeterminado' => 'Indeterminado',
                        'inicio_incremento_actividad' => 'Inicio o incremento de actividad',
                        'necesidad_mercado' => 'Necesidad de mercado',
                        'obra_servicio_especifico' => 'Obra determinada o servicio específico',
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $tipo) => $q->whereHas('employee', fn ($e) => $e->where('tipo_contrato', $tipo))
                    )),
            ])
            ->defaultSort('renovado_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContractRenewals::route('/'),
        ];
    }
}
