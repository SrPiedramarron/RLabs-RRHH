<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParametroLegalResource\Pages;
use App\Models\ParametroLegal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ParametroLegalResource extends Resource
{
    protected static ?string $model            = ParametroLegal::class;
    protected static ?string $slug             = 'parametros-legales';
    protected static ?string $navigationIcon   = 'heroicon-o-scale';
    protected static ?string $navigationLabel  = 'Parámetros Legales';
    protected static ?string $navigationGroup  = 'Administración';
    protected static ?int    $navigationSort   = 50;
    protected static ?string $modelLabel       = 'Parámetro legal';
    protected static ?string $pluralModelLabel = 'Parámetros Legales';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('clave')
                ->label('Parámetro')
                ->options([
                    ParametroLegal::RMV      => 'RMV — Remuneración Mínima Vital (S/)',
                    ParametroLegal::UIT      => 'UIT — Unidad Impositiva Tributaria (S/)',
                    ParametroLegal::TASA_ONP => 'Tasa ONP (ej. 0.13 = 13%)',
                ])
                ->required()
                ->native(false),

            Forms\Components\TextInput::make('valor')
                ->label('Valor')
                ->numeric()
                ->required()
                ->helperText('Para la tasa ONP ingresa el decimal (0.13), no el porcentaje (13).'),

            Forms\Components\DatePicker::make('vigente_desde')
                ->label('Vigente desde')
                ->required()
                ->native(false)
                ->helperText('Desde esta fecha se usa este valor. No borres los valores anteriores — el sistema los necesita para recalcular correctamente periodos pasados.'),

            Forms\Components\DatePicker::make('vigente_hasta')
                ->label('Vigente hasta (opcional)')
                ->native(false)
                ->helperText('Déjalo vacío si este es el valor vigente actualmente. Al agregar un valor nuevo con una fecha "Vigente desde" posterior, cierra el anterior poniéndole aquí el día justo antes.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('clave')
                    ->label('Parámetro')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        ParametroLegal::RMV      => 'RMV',
                        ParametroLegal::UIT      => 'UIT',
                        ParametroLegal::TASA_ONP => 'Tasa ONP',
                        default                   => $state,
                    })
                    ->badge(),

                Tables\Columns\TextColumn::make('valor')
                    ->label('Valor')
                    ->numeric(decimalPlaces: 4),

                Tables\Columns\TextColumn::make('vigente_desde')
                    ->label('Vigente desde')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('vigente_hasta')
                    ->label('Vigente hasta')
                    ->date('d/m/Y')
                    ->placeholder('Actual')
                    ->sortable(),
            ])
            ->defaultSort('vigente_desde', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('clave')
                    ->label('Parámetro')
                    ->options([
                        ParametroLegal::RMV      => 'RMV',
                        ParametroLegal::UIT      => 'UIT',
                        ParametroLegal::TASA_ONP => 'Tasa ONP',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListParametrosLegales::route('/'),
            'create' => Pages\CreateParametroLegal::route('/create'),
            'edit'   => Pages\EditParametroLegal::route('/{record}/edit'),
        ];
    }
}
