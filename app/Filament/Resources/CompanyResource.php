<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Empresas';
    protected static ?string $modelLabel = 'Empresa';
    protected static ?string $pluralModelLabel = 'Empresas';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        
        return $form->schema([
            Forms\Components\Section::make('Datos de la Empresa')
                ->schema([
                    Forms\Components\TextInput::make('razon_social')
                        ->label('Razón Social')
                        ->required()
                        ->maxLength(200)
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('ruc')
                        ->label('RUC')
                        ->required()
                        ->length(11)
                        ->numeric(),

                    Forms\Components\TextInput::make('telefono')
                        ->label('Teléfono')
                        ->maxLength(20),

                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->maxLength(100)
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('direccion')
                        ->label('Dirección')
                        ->maxLength(300)
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('ubigeo')
                        ->label('Ubigeo')
                        ->maxLength(6),

                    Forms\Components\Toggle::make('active')
                        ->label('Activa')
                        ->default(true),
                ])->columns(2),

            Forms\Components\Section::make('Logo')
                ->schema([
                    Forms\Components\FileUpload::make('logo_path')
                        ->label('Logo de la empresa')
                        ->image()
                        ->directory('logos')
                        ->maxSize(2048),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->circular(),

                Tables\Columns\TextColumn::make('razon_social')
                    ->label('Razón Social')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ruc')
                    ->label('RUC')
                    ->searchable(),

                Tables\Columns\TextColumn::make('telefono')
                    ->label('Teléfono'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email'),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activa')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Estado')
                    ->trueLabel('Solo activas')
                    ->falseLabel('Solo inactivas'),
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
            'index'  => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit'   => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}