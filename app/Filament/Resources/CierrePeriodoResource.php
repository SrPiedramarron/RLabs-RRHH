<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CierrePeriodoResource\Pages;
use App\Filament\Traits\HasCompanyScope;
use App\Helpers\CompanyContext;
use App\Models\CierrePeriodo;
use App\Models\Company;
use App\Support\EstadisticasAsistencia;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CierrePeriodoResource extends Resource
{
    use HasCompanyScope;

    protected static ?string $model            = CierrePeriodo::class;
    protected static ?string $navigationIcon   = 'heroicon-o-lock-closed';
    protected static ?string $navigationLabel  = 'Cierre de periodos';
    protected static ?string $navigationGroup  = 'Planilla';
    protected static ?int    $navigationSort   = 90;
    protected static ?string $modelLabel       = 'Cierre de periodo';
    protected static ?string $pluralModelLabel = 'Cierre de periodos';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company.razon_social')->label('Empresa')->searchable(),
                Tables\Columns\TextColumn::make('periodo')->label('Periodo')->sortable(),
                Tables\Columns\IconColumn::make('cerrado')->label('Cerrado')->boolean()
                    ->trueIcon('heroicon-o-lock-closed')->falseIcon('heroicon-o-lock-open')
                    ->trueColor('danger')->falseColor('success'),
                Tables\Columns\TextColumn::make('cerrado_at')->label('Cerrado el')->dateTime('d/m/Y H:i')->placeholder('—'),
                Tables\Columns\TextColumn::make('cerradoPor.name')->label('Cerrado por')->placeholder('—'),
                Tables\Columns\TextColumn::make('reabierto_at')->label('Reabierto el')->dateTime('d/m/Y H:i')->placeholder('—'),
                Tables\Columns\TextColumn::make('motivo_reapertura')->label('Motivo de reapertura')->limit(50)->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('cerrar')
                    ->label('Cerrar periodo')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->form([
                        Forms\Components\Select::make('company_id')->label('Empresa')
                            ->options(fn () => Company::orderBy('razon_social')->pluck('razon_social', 'id'))
                            ->default(CompanyContext::get())->required()->native(false),
                        Forms\Components\Select::make('periodo')->label('Mes a cerrar')
                            ->options(EstadisticasAsistencia::meses())->required()->native(false),
                        Forms\Components\Placeholder::make('aviso')->label('')
                            ->content('Con el mes cerrado no se podrá recalcular planilla, quincena, honorarios, gratificación, CTS ni utilidades de ese mes. Lo ya calculado queda tal cual. Solo un super administrador puede reabrirlo.'),
                    ])
                    ->requiresConfirmation()
                    ->action(function (array $data) {
                        CierrePeriodo::updateOrCreate(
                            ['company_id' => $data['company_id'], 'periodo' => $data['periodo']],
                            ['cerrado' => true, 'cerrado_por' => auth()->id(), 'cerrado_at' => now()],
                        );
                        Notification::make()->title("Periodo {$data['periodo']} cerrado")->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('reabrir')
                    ->label('Reabrir')
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->visible(fn (CierrePeriodo $r) => $r->cerrado && auth()->user()?->hasRole('super_admin'))
                    ->form([
                        Forms\Components\Textarea::make('motivo')->label('Motivo de la reapertura')->required()->rows(3),
                    ])
                    ->modalDescription('Al reabrir, el mes se podrá recalcular con los datos y parámetros de hoy.')
                    ->action(function (CierrePeriodo $r, array $data) {
                        $r->update([
                            'cerrado' => false, 'reabierto_por' => auth()->id(),
                            'reabierto_at' => now(), 'motivo_reapertura' => $data['motivo'],
                        ]);
                        Notification::make()->title("Periodo {$r->periodo} reabierto")->warning()->send();
                    }),
                Tables\Actions\Action::make('cerrar_de_nuevo')
                    ->label('Volver a cerrar')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn (CierrePeriodo $r) => ! $r->cerrado)
                    ->requiresConfirmation()
                    ->action(fn (CierrePeriodo $r) => $r->update(['cerrado' => true, 'cerrado_por' => auth()->id(), 'cerrado_at' => now()])),
            ])
            ->defaultSort('periodo', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCierresPeriodo::route('/')];
    }
}
