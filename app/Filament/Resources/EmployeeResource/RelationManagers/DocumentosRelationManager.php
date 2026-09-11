<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentosRelationManager extends RelationManager
{
    protected static string $relationship = 'documentos';

    protected static ?string $title = 'Documentos';

    protected static ?string $icon = 'heroicon-o-document-text';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('tipo')
                ->label('Tipo de documento')
                ->required()
                ->maxLength(100)
                ->helperText('Ej: Contrato de trabajo, DNI, CV, Adenda, etc. (por ahora es texto libre — cuando RRHH defina la lista fija de documentos, se convierte en un selector).')
                ->datalist([
                    'Contrato de trabajo',
                    'Adenda / Renovación de contrato',
                    'DNI',
                    'Curriculum Vitae',
                    'Certificado de antecedentes',
                    'Otro',
                ]),

            Forms\Components\TextInput::make('nombre')
                ->label('Descripción (opcional)')
                ->maxLength(150)
                ->helperText('Ej: "Contrato renovado 2026" — para diferenciar cuando hay varios del mismo tipo.'),

            Forms\Components\DatePicker::make('fecha_documento')
                ->label('Fecha del documento')
                ->displayFormat('d/m/Y')
                ->helperText('Ej: fecha de firma del contrato.'),

            Forms\Components\FileUpload::make('archivo')
                ->label('Archivo')
                ->required()
                ->directory('trabajadores/documentos')
                ->visibility('private')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                ->maxSize(10240)
                ->downloadable()
                ->openable(),

            Forms\Components\Textarea::make('observacion')
                ->label('Observación')
                ->maxLength(500),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tipo')
            ->columns([
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Descripción')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('fecha_documento')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Subido el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Subir documento'),
            ])
            ->actions([
                Tables\Actions\Action::make('ver')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => $record->archivo_url)
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Sin documentos')
            ->emptyStateDescription('Aún no se ha subido ningún documento para este trabajador.');
    }
}
