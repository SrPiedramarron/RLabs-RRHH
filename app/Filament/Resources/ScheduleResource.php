<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScheduleResource\Pages;
use App\Models\Schedule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Traits\HasCompanyScope;

class ScheduleResource extends Resource
{
    use HasCompanyScope;
    protected static ?string $model = Schedule::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Horarios';
    protected static ?string $modelLabel = 'Horario';
    protected static ?string $pluralModelLabel = 'Horarios';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 40;

    public static function form(Form $form): Form
    {
        
        return $form->schema([
            Forms\Components\Section::make('Datos del Horario')
                ->schema([
                    Forms\Components\Select::make('company_id')
                        ->label('Empresa')
                        ->relationship('company', 'razon_social')
                        ->required()
                        ->searchable()
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('nombre')
                        ->label('Nombre del Horario')
                        ->placeholder('Jornada 8h, Turno Noche...')
                        ->required()
                        ->maxLength(100)
                        ->columnSpan(2),

                    Forms\Components\TimePicker::make('hora_entrada')
                        ->label('Hora de Entrada')
                        ->required()
                        ->seconds(false),

                    Forms\Components\TimePicker::make('hora_salida')
                        ->label('Hora de Salida')
                        ->required()
                        ->seconds(false),

                    Forms\Components\TextInput::make('tolerancia_minutos')
                        ->label('Tolerancia (minutos)')
                        ->numeric()
                        ->default(5)
                        ->minValue(0)
                        ->maxValue(60),

                    Forms\Components\Toggle::make('es_nocturno')
                        ->label('Turno Nocturno')
                        ->default(false),
                ])->columns(2),

            Forms\Components\Section::make('Refrigerio')
                ->schema([
                    Forms\Components\TimePicker::make('refrigerio_inicio')
                        ->label('Inicio Refrigerio')
                        ->seconds(false),

                    Forms\Components\TimePicker::make('refrigerio_fin')
                        ->label('Fin Refrigerio')
                        ->seconds(false),
                ])->columns(2),

            Forms\Components\Section::make('Días Laborables')
                ->schema([
                    Forms\Components\CheckboxList::make('dias_laborables')
                        ->label('')
                        ->options([
                            1 => 'Lunes',
                            2 => 'Martes',
                            3 => 'Miércoles',
                            4 => 'Jueves',
                            5 => 'Viernes',
                            6 => 'Sábado',
                            7 => 'Domingo',
                        ])
                        ->columns(4)
                        ->default([1, 2, 3, 4, 5]),
                ]),
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

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Horario')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('hora_entrada')
                    ->label('Entrada'),

                Tables\Columns\TextColumn::make('hora_salida')
                    ->label('Salida'),

                Tables\Columns\TextColumn::make('tolerancia_minutos')
                    ->label('Tolerancia')
                    ->suffix(' min'),

                Tables\Columns\TextColumn::make('nombre_dias')
                    ->label('Días')
                    ->getStateUsing(fn($record) => $record->nombre_dias),

                Tables\Columns\IconColumn::make('es_nocturno')
                    ->label('Nocturno')
                    ->boolean(),

                Tables\Columns\TextColumn::make('employees_count')
                    ->label('Trabajadores')
                    ->counts('employees')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Empresa')
                    ->relationship('company', 'razon_social'),

                Tables\Filters\TernaryFilter::make('es_nocturno')
                    ->label('Turno Nocturno'),
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
            'index'  => Pages\ListSchedules::route('/'),
            'create' => Pages\CreateSchedule::route('/create'),
            'edit'   => Pages\EditSchedule::route('/{record}/edit'),
        ];
    }
}