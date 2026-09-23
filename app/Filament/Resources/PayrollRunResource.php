<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollRunResource\Actions\GenerateBbvaFileAction;
use App\Filament\Resources\PayrollRunResource\Actions\GenerateBcpFileAction;
use App\Filament\Resources\PayrollRunResource\Pages;
use App\Filament\Resources\PayrollRunResource\RelationManagers;
use App\Models\PayrollRun;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PayrollRunResource extends Resource
{
    protected static ?string $model = PayrollRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Pagos Bancarios';
    protected static ?string $modelLabel = 'Pago bancario';
    protected static ?string $pluralModelLabel = 'Pagos Bancarios';
    protected static ?string $navigationGroup = 'Pagos Bancarios';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la Planilla')
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->relationship('company', 'razon_social')
                            ->searchable()
                            ->required()
                            ->columnSpan(2),

                        Forms\Components\Select::make('periodo_tipo')
                            ->label('Tipo de periodo')
                            ->options([
                                'quincenal' => 'Quincenal',
                                'mensual' => 'Mensual',
                            ])
                            ->required()
                            ->live()
                            ->native(false),

                        Forms\Components\Select::make('quincena')
                            ->label('Quincena')
                            ->options([1 => '1ra quincena', 2 => '2da quincena'])
                            ->visible(fn (Forms\Get $get) => $get('periodo_tipo') === 'quincenal')
                            ->required(fn (Forms\Get $get) => $get('periodo_tipo') === 'quincenal')
                            ->native(false),

                        Forms\Components\Select::make('mes')
                            ->label('Mes')
                            ->options([
                                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                                9 => 'Setiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
                            ])
                            ->required()
                            ->native(false),

                        Forms\Components\TextInput::make('anio')
                            ->label('Año')
                            ->numeric()
                            ->required()
                            ->default(now()->year),

                        Forms\Components\DatePicker::make('fecha_inicio')
                            ->label('Fecha inicio')
                            ->required(),

                        Forms\Components\DatePicker::make('fecha_fin')
                            ->label('Fecha fin')
                            ->required(),

                        Forms\Components\DatePicker::make('fecha_pago')
                            ->label('Fecha de pago')
                            ->required(),

                        Forms\Components\Select::make('moneda')
                            ->label('Moneda')
                            ->options(['PEN' => 'Soles (PEN)', 'USD' => 'Dólares (USD)'])
                            ->default('PEN')
                            ->required()
                            ->native(false),

                        Forms\Components\TextInput::make('cuenta_cargo_pago')
                            ->label('Cuenta de cargo (banco)')
                            ->maxLength(22)
                            ->helperText('Cuenta de la empresa desde la que se cargan los pagos, usada para el archivo bancario.'),

                        Forms\Components\TextInput::make('referencia')
                            ->label('Referencia')
                            ->maxLength(25)
                            ->helperText('Ej. "1ERA QUINCENA SETIEMBRE" — aparece en el archivo bancario.')
                            ->columnSpan(2),

                        Forms\Components\Select::make('estado')
                            ->label('Estado')
                            ->options([
                                'borrador' => 'Borrador',
                                'aprobada' => 'Aprobada',
                                'pagada' => 'Pagada',
                            ])
                            ->default('borrador')
                            ->required()
                            ->native(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company.razon_social')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('periodo_tipo')
                    ->label('Periodo')
                    ->formatStateUsing(fn (string $state, PayrollRun $record): string => $state === 'quincenal'
                        ? "Q{$record->quincena} " . str_pad((string) $record->mes, 2, '0', STR_PAD_LEFT) . "/{$record->anio}"
                        : str_pad((string) $record->mes, 2, '0', STR_PAD_LEFT) . "/{$record->anio}"),

                Tables\Columns\TextColumn::make('fecha_pago')
                    ->label('Fecha de pago')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('moneda')
                    ->label('Moneda'),

                Tables\Columns\TextColumn::make('entries_count')
                    ->label('Trabajadores')
                    ->counts('entries')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'borrador' => 'gray',
                        'aprobada' => 'warning',
                        'pagada' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Empresa')
                    ->relationship('company', 'razon_social'),

                Tables\Filters\SelectFilter::make('estado')
                    ->options([
                        'borrador' => 'Borrador',
                        'aprobada' => 'Aprobada',
                        'pagada' => 'Pagada',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                GenerateBbvaFileAction::make(),
                GenerateBcpFileAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EntriesRelationManager::class,
            RelationManagers\PaymentFilesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollRuns::route('/'),
            'create' => Pages\CreatePayrollRun::route('/create'),
            'edit' => Pages\EditPayrollRun::route('/{record}/edit'),
        ];
    }
}
