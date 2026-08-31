<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    /**
     * NO usa el trait genérico HasCompanyScope a propósito: super_admin
     * necesita ver usuarios de TODAS las empresas (no está atado a una),
     * mientras que otros roles sí deben quedar filtrados por su empresa
     * activa. El trait genérico no distingue esto.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->user()?->hasRole('super_admin')) {
            return $query;
        }

        $companyId = \App\Helpers\CompanyContext::get();

        return $companyId ? $query->where('company_id', $companyId) : $query;
    }

    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Usuarios';
    protected static ?string $modelLabel = 'Usuario';
    protected static ?string $pluralModelLabel = 'Usuarios';
    protected static ?string $navigationGroup = 'Administración';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos del Usuario')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre completo')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->label('Correo electrónico')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    Forms\Components\TextInput::make('password')
                        ->label('Contraseña')
                        ->password()
                        ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->minLength(8)
                        ->hint('Mínimo 8 caracteres. Dejar en blanco para no cambiar.'),

                    // FALTABA POR COMPLETO — sin esto, un usuario nuevo se
                    // creaba sin empresa asignada y quedaba visible/editable
                    // desde cualquier empresa activa. OPCIONAL: un super_admin
                    // puede dejarse sin empresa para tener acceso global.
                    Forms\Components\Select::make('company_id')
                        ->label('Empresa')
                        ->relationship('company', 'razon_social')
                        ->searchable()
                        ->helperText('Dejar vacío solo para usuarios super_admin con acceso a todas las empresas.'),

                    Forms\Components\Toggle::make('active')
                        ->label('Activo')
                        ->default(true),
                ])->columns(2),

            Forms\Components\Section::make('Roles y Permisos')
                ->schema([
                    Forms\Components\Select::make('roles')
                        ->label('Roles')
                        ->relationship('roles', 'name')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->getOptionLabelFromRecordUsing(fn ($record) => str($record->name)->headline()),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('company.razon_social')
                    ->label('Empresa')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->formatStateUsing(fn ($state) => str($state)->headline())
                    ->color(fn ($state) => match ($state) {
                        'Super Admin'   => 'danger',
                        'Gerente'       => 'warning',
                        'Rrhh'          => 'success',
                        'Contabilidad'  => 'info',
                        default         => 'gray',
                    }),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Rol')
                    ->relationship('roles', 'name')
                    ->preload(),
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
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}