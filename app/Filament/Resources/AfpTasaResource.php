<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AfpTasaResource\Pages;
use App\Models\AfpTasa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AfpTasaResource extends Resource
{
    protected static ?string $model            = AfpTasa::class;
    protected static ?string $slug             = 'tasas-afp';
    protected static ?string $navigationIcon   = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel  = 'Tasas AFP';
    protected static ?string $navigationGroup  = 'Administración';
    protected static ?int    $navigationSort   = 51;
    protected static ?string $modelLabel       = 'Tasa AFP';
    protected static ?string $pluralModelLabel = 'Tasas AFP';

    private const AFPS = [
        'afp_habitat'   => 'AFP Habitat',
        'afp_integra'   => 'AFP Integra',
        'afp_prima'     => 'AFP Prima',
        'afp_profuturo' => 'AFP Profuturo',
    ];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('afp')->label('AFP')->options(self::AFPS)
                ->required()->native(false),
            Forms\Components\TextInput::make('tope_remuneracion_asegurable')
                ->label('Remuneración máxima asegurable (S/)')
                ->numeric()->required()
                ->helperText('Tope sobre el que se calcula la prima de seguro. La SBS lo actualiza cada 3 meses.'),
            Forms\Components\TextInput::make('comision_flujo')->label('Comisión sobre flujo (decimal, ej. 0.0147)')
                ->numeric()->required(),
            Forms\Components\TextInput::make('prima_seguro')->label('Prima de seguro (decimal, ej. 0.0137)')
                ->numeric()->required(),
            Forms\Components\TextInput::make('aporte_obligatorio')->label('Aporte obligatorio (decimal, ej. 0.10)')
                ->numeric()->required(),
            Forms\Components\DatePicker::make('vigente_desde')->label('Vigente desde')
                ->required()->native(false)
                ->helperText('No borres los valores anteriores: se usan para recalcular periodos pasados. Al agregar uno nuevo, cierra el anterior poniéndole "Vigente hasta" el día previo.'),
            Forms\Components\DatePicker::make('vigente_hasta')->label('Vigente hasta (opcional)')
                ->native(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('afp')->label('AFP')
                    ->formatStateUsing(fn (string $state) => self::AFPS[$state] ?? $state)->badge(),
                Tables\Columns\TextColumn::make('tope_remuneracion_asegurable')->label('Máx. asegurable')->numeric(2),
                Tables\Columns\TextColumn::make('comision_flujo')->label('Com. flujo')
                    ->formatStateUsing(fn ($state) => number_format($state * 100, 2) . '%'),
                Tables\Columns\TextColumn::make('prima_seguro')->label('Prima')
                    ->formatStateUsing(fn ($state) => number_format($state * 100, 2) . '%'),
                Tables\Columns\TextColumn::make('aporte_obligatorio')->label('Aporte')
                    ->formatStateUsing(fn ($state) => number_format($state * 100, 2) . '%'),
                Tables\Columns\TextColumn::make('vigente_desde')->label('Desde')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('vigente_hasta')->label('Hasta')->date('d/m/Y')->placeholder('Actual'),
            ])
            ->defaultSort('vigente_desde', 'desc')
            ->filters([Tables\Filters\SelectFilter::make('afp')->label('AFP')->options(self::AFPS)])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAfpTasas::route('/'),
            'create' => Pages\CreateAfpTasa::route('/create'),
            'edit'   => Pages\EditAfpTasa::route('/{record}/edit'),
        ];
    }
}
