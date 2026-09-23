<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollConceptResource\Pages;
use App\Models\PayrollConcept;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PayrollConceptResource extends Resource
{
    protected static ?string $model = PayrollConcept::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';
    protected static ?string $navigationLabel = 'Conceptos de Pago';
    protected static ?string $modelLabel = 'Concepto';
    protected static ?string $pluralModelLabel = 'Conceptos de Pago';
    protected static ?string $navigationGroup = 'Pagos Bancarios';
    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos del Concepto')
                ->schema([
                    Forms\Components\TextInput::make('codigo')
                        ->label('Código')
                        ->required()
                        ->maxLength(20)
                        ->unique(ignoreRecord: true)
                        ->helperText('Identificador único, ej. SUELDO_BASICO, ONP.'),

                    Forms\Components\TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(100),

                    Forms\Components\Select::make('tipo')
                        ->label('Tipo')
                        ->options([
                            'ingreso' => 'Ingreso',
                            'descuento' => 'Descuento',
                        ])
                        ->required()
                        ->native(false),

                    Forms\Components\Toggle::make('afecto_onp_afp')
                        ->label('Afecto a ONP/AFP')
                        ->helperText('Forma parte de la base de cálculo de la retención de pensiones.'),

                    Forms\Components\Toggle::make('afecto_renta_5ta')
                        ->label('Afecto a Renta de 5ta categoría')
                        ->helperText('Forma parte de la base de cálculo de la retención de renta.'),

                    Forms\Components\Toggle::make('active')
                        ->label('Activo')
                        ->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'ingreso' ? 'success' : 'danger')
                    ->formatStateUsing(fn (string $state): string => $state === 'ingreso' ? 'Ingreso' : 'Descuento'),

                Tables\Columns\IconColumn::make('afecto_onp_afp')
                    ->label('ONP/AFP')
                    ->boolean(),

                Tables\Columns\IconColumn::make('afecto_renta_5ta')
                    ->label('Renta 5ta')
                    ->boolean(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'ingreso' => 'Ingreso',
                        'descuento' => 'Descuento',
                    ]),

                Tables\Filters\TernaryFilter::make('active')
                    ->label('Estado')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos'),
            ])
            ->defaultSort('codigo')
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
            'index' => Pages\ListPayrollConcepts::route('/'),
            'create' => Pages\CreatePayrollConcept::route('/create'),
            'edit' => Pages\EditPayrollConcept::route('/{record}/edit'),
        ];
    }
}
