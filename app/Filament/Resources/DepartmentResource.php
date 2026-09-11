<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepartmentResource\Pages;
use App\Models\Department;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Traits\HasCompanyScope;


class DepartmentResource extends Resource
{
    use HasCompanyScope;
    protected static ?string $model = Department::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Áreas';
    protected static ?string $modelLabel = 'Área';
    protected static ?string $pluralModelLabel = 'Áreas';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        
        return $form->schema([
            Forms\Components\Section::make('Datos del Área')
                ->schema([
                    Forms\Components\Select::make('company_id')
                        ->label('Empresa')
                        ->relationship('company', 'razon_social')
                        ->required()
                        ->searchable()
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('nombre')
                        ->label('Nombre del Área')
                        ->required()
                        ->maxLength(100),
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

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Área')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('employees_count')
                    ->label('Trabajadores')
                    ->counts('employees')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
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
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'edit'   => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }
}