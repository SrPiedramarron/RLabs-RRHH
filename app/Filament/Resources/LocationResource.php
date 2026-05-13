<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocationResource\Pages;
use App\Models\Location;
use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Traits\HasCompanyScope;

class LocationResource extends Resource
{
    use HasCompanyScope;
    protected static ?string $model = Location::class;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationLabel = 'Sedes';
    protected static ?string $modelLabel = 'Sede';
    protected static ?string $pluralModelLabel = 'Sedes';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos de la Sede')
                ->schema([
                    Forms\Components\Select::make('company_id')
                        ->label('Empresa')
                        ->relationship('company', 'razon_social')
                        ->required()
                        ->searchable()
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('nombre')
                        ->label('Nombre de la Sede')
                        ->required()
                        ->maxLength(100),

                    Forms\Components\TextInput::make('ubigeo')
                        ->label('Ubigeo')
                        ->maxLength(6),

                    Forms\Components\TextInput::make('direccion')
                        ->label('Dirección')
                        ->maxLength(300)
                        ->columnSpan(2),

                    Forms\Components\Toggle::make('active')
                        ->label('Activa')
                        ->default(true),
                ])->columns(2),

            Forms\Components\Section::make('Configuración del Reloj ZKTeco')
                ->schema([
                    Forms\Components\TextInput::make('reloj_ip')
                        ->label('IP del Reloj')
                        ->placeholder('192.168.88.251')
                        ->maxLength(15),

                    Forms\Components\TextInput::make('reloj_puerto')
                        ->label('Puerto')
                        ->numeric()
                        ->default(4370),

                    Forms\Components\TextInput::make('reloj_modelo')
                        ->label('Modelo del Reloj')
                        ->placeholder('ZKTeco K40')
                        ->maxLength(50),

                    Forms\Components\Select::make('reloj_tipo')
                        ->label('Tipo de Conexión')
                        ->options([
                            'zkbio' => 'ZKBio HTTP API (Sede con túnel HTTP)',
                            'zksdk' => 'ZKTeco SDK (Protocolo nativo puerto 4370)',
                            'zkadms' => 'ZKTeco ADMS (Push HTTP - modelos nuevos)',
                        ])
                        ->default('zkbio')
                        ->required(),

                    Forms\Components\Toggle::make('reloj_activo')
                        ->label('Reloj Activo')
                        ->default(true),
                ])->columns(2),

            Forms\Components\Section::make('Estado de Sincronización')
                ->schema([
                    Forms\Components\Placeholder::make('ultima_sync')
                        ->label('Última Sincronización')
                        ->content(fn($record) => $record?->ultima_sync?->format('d/m/Y H:i:s') ?? 'Nunca'),

                    Forms\Components\Placeholder::make('sync_estado')
                        ->label('Estado')
                        ->content(fn($record) => match($record?->sync_estado) {
                            'ok'       => '✅ OK',
                            'error'    => '❌ Error',
                            'pendiente'=> '⏳ Pendiente',
                            default    => '—'
                        }),

                    Forms\Components\Placeholder::make('sync_error_msg')
                        ->label('Último Error')
                        ->content(fn($record) => $record?->sync_error_msg ?? '—')
                        ->columnSpan(2),
                ])
                ->columns(2)
                ->visibleOn('edit'),
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
                    ->label('Sede')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('reloj_ip')
                    ->label('IP Reloj'),

                Tables\Columns\TextColumn::make('reloj_modelo')
                    ->label('Modelo'),

                Tables\Columns\BadgeColumn::make('reloj_tipo')
                    ->label('Tipo')
                    ->colors([
                        'primary' => 'zkbio',
                        'warning' => 'zksdk',
                    ]),

                Tables\Columns\BadgeColumn::make('sync_estado')
                    ->label('Sync')
                    ->colors([
                        'success' => 'ok',
                        'danger'  => 'error',
                        'warning' => 'pendiente',
                    ]),

                Tables\Columns\TextColumn::make('ultima_sync')
                    ->label('Última Sync')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\IconColumn::make('reloj_activo')
                    ->label('Reloj')
                    ->boolean(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Empresa')
                    ->relationship('company', 'razon_social'),

                Tables\Filters\TernaryFilter::make('reloj_activo')
                    ->label('Reloj Activo'),
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
            'index'  => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'edit'   => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}