<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecordatorioResource\Pages;
use App\Filament\Traits\HasCompanyScope;
use App\Models\Recordatorio;
use App\Services\RecordatorioService;
use App\Support\Recordatorios;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RecordatorioResource extends Resource
{
    use HasCompanyScope;

    protected static ?string $model            = Recordatorio::class;
    protected static ?string $slug             = 'recordatorios';
    protected static ?string $navigationIcon   = 'heroicon-o-bell-alert';
    protected static ?string $navigationLabel  = 'Recordatorios';
    protected static ?string $navigationGroup  = 'Administración';
    protected static ?int    $navigationSort   = 60;
    protected static ?string $modelLabel       = 'Recordatorio';
    protected static ?string $pluralModelLabel = 'Recordatorios por correo';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('company_id')
                ->label('Empresa')
                ->relationship('company', 'razon_social')
                ->required()
                ->searchable(),

            Forms\Components\Select::make('tipo')
                ->label('Recordatorio')
                ->options(Recordatorios::opciones())
                ->required()
                ->native(false)
                ->live()
                ->helperText(fn (Forms\Get $get) => Recordatorios::TIPOS[$get('tipo')]['ayuda'] ?? null),

            Forms\Components\Textarea::make('destinatarios')
                ->label('Correos que lo reciben')
                ->required()
                ->rows(3)
                ->columnSpanFull()
                ->helperText('Sepáralos con punto y coma, coma o salto de línea. Ej.: rrhh@empresa.com; gerencia@empresa.com')
                ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                    $partes = preg_split('/[;,\s]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
                    if (! $partes) {
                        $fail('Escribe al menos un correo.');
                    }
                    foreach ($partes as $p) {
                        if (! filter_var($p, FILTER_VALIDATE_EMAIL)) {
                            $fail("«{$p}» no es un correo válido.");
                        }
                    }
                }),

            Forms\Components\Select::make('modo')
                ->label('¿Cuándo se envía?')
                ->options(Recordatorios::MODOS)
                ->required()
                ->native(false)
                ->live()
                ->default('dia_mes'),

            Forms\Components\TextInput::make('dia_mes')
                ->label('Día del mes')
                ->numeric()->minValue(1)->maxValue(31)
                ->visible(fn (Forms\Get $get) => $get('modo') === 'dia_mes')
                ->required(fn (Forms\Get $get) => $get('modo') === 'dia_mes')
                ->helperText('31 = el último día del mes (también sirve en febrero).'),

            Forms\Components\TextInput::make('dias_antes')
                ->label('Cuántos días antes')
                ->numeric()->minValue(0)->maxValue(90)
                ->visible(fn (Forms\Get $get) => $get('modo') === 'dias_antes')
                ->required(fn (Forms\Get $get) => $get('modo') === 'dias_antes')
                ->helperText('1 = un día antes (24 horas). 0 = el mismo día.'),

            Forms\Components\TextInput::make('ventana_dias')
                ->label('Días hacia adelante que cubre el aviso')
                ->numeric()->minValue(0)->maxValue(365)
                ->visible(fn (Forms\Get $get) => $get('modo') !== 'dias_antes')
                ->helperText('Ej.: 30 avisa lo que ocurre en los próximos 30 días. Vacío: en el aviso mensual, lo que queda del mes; en el diario, solo lo de hoy.'),

            Forms\Components\TextInput::make('hora')
                ->label('Hora de envío')
                ->type('time')
                ->required()
                ->default('08:00'),

            Forms\Components\Toggle::make('activo')
                ->label('Activo')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tipo')->label('Recordatorio')
                    ->formatStateUsing(fn ($state) => Recordatorios::TIPOS[$state]['label'] ?? $state)->badge(),
                Tables\Columns\TextColumn::make('company.razon_social')->label('Empresa')->searchable(),
                Tables\Columns\TextColumn::make('cuando')->label('Cuándo'),
                Tables\Columns\TextColumn::make('destinatarios')->label('Correos')->limit(60)->wrap(),
                Tables\Columns\IconColumn::make('activo')->label('Activo')->boolean(),
                Tables\Columns\TextColumn::make('ultimo_envio_at')->label('Último envío')->dateTime('d/m/Y H:i')->placeholder('Nunca'),
            ])
            ->actions([
                Tables\Actions\Action::make('prueba')
                    ->label('Enviar prueba')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Envía ahora un correo de prueba a todos los destinatarios, con lo que correspondería avisar hoy.')
                    ->action(function (Recordatorio $record) {
                        try {
                            $n = app(RecordatorioService::class)->enviar($record, prueba: true);
                            Notification::make()->title('Prueba enviada')->body($n ? "{$n} elemento(s) incluidos." : 'Hoy no hay elementos que avisar; se envió igual el correo vacío.')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('No se pudo enviar')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('tipo');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRecordatorios::route('/'),
            'create' => Pages\CreateRecordatorio::route('/create'),
            'edit'   => Pages\EditRecordatorio::route('/{record}/edit'),
        ];
    }
}
