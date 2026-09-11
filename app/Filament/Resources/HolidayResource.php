<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HolidayResource\Pages;
use App\Models\Holiday;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HolidayResource extends Resource
{
    protected static ?string $model = Holiday::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Feriados';
    protected static ?string $modelLabel = 'Feriado';
    protected static ?string $pluralModelLabel = 'Feriados';
    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if ($user->isSuperAdmin() || is_null($user->company_id)) {
            return $query;
        }

        return $query->where(function($q) use ($user) {
            $q->whereNull('company_id')
            ->orWhere('company_id', $user->company_id);
        });
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos del Feriado')
                ->schema([
                    Forms\Components\Select::make('tipo')
                        ->label('Tipo')
                        ->options([
                            'nacional'  => 'Nacional',
                            'regional'  => 'Regional',
                            'empresa'   => 'Empresa',
                        ])
                        ->required()
                        ->live()
                        ->default('nacional'),

                    Forms\Components\DatePicker::make('fecha')
                        ->label('Fecha')
                        ->required()
                        ->displayFormat('d/m/Y'),

                    Forms\Components\TextInput::make('nombre')
                        ->label('Nombre del Feriado')
                        ->placeholder('Año Nuevo, Navidad...')
                        ->required()
                        ->maxLength(100)
                        ->columnSpan(2),

                    Forms\Components\Select::make('company_id')
                        ->label('Empresa')
                        ->relationship('company', 'razon_social')
                        ->searchable()
                        ->nullable()
                        ->helperText('Solo requerido para feriados de empresa')
                        ->visible(fn(Forms\Get $get) => $get('tipo') === 'empresa'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Feriado')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('tipo')
                    ->label('Tipo')
                    ->colors([
                        'danger'  => 'nacional',
                        'warning' => 'regional',
                        'info'    => 'empresa',
                    ]),

                Tables\Columns\TextColumn::make('company.razon_social')
                    ->label('Empresa')
                    ->placeholder('Todos')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'nacional'  => 'Nacional',
                        'regional'  => 'Regional',
                        'empresa'   => 'Empresa',
                    ]),

                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Empresa')
                    ->relationship('company', 'razon_social'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('fecha', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListHolidays::route('/'),
            'create' => Pages\CreateHoliday::route('/create'),
            'edit'   => Pages\EditHoliday::route('/{record}/edit'),
        ];
    }
}