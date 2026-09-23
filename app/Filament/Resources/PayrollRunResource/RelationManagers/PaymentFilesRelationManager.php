<?php

namespace App\Filament\Resources\PayrollRunResource\RelationManagers;

use App\Models\PaymentFile;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class PaymentFilesRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentFiles';

    protected static ?string $title = 'Archivos bancarios generados';

    protected static ?string $icon = 'heroicon-o-document-arrow-down';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('archivo_path')
            ->columns([
                Tables\Columns\TextColumn::make('banco')
                    ->label('Banco')
                    ->badge(),

                Tables\Columns\TextColumn::make('archivo_path')
                    ->label('Archivo')
                    ->formatStateUsing(fn (string $state) => basename($state))
                    ->searchable(),

                Tables\Columns\TextColumn::make('cantidad_registros')
                    ->label('Registros')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('monto_total')
                    ->label('Monto total')
                    ->money('PEN'),

                Tables\Columns\TextColumn::make('generadoPor.name')
                    ->label('Generado por')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Generado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('descargar')
                    ->label('Descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (PaymentFile $record) => Storage::disk('local')->exists($record->archivo_path))
                    ->action(fn (PaymentFile $record) => Storage::disk('local')->download(
                        $record->archivo_path,
                        basename($record->archivo_path)
                    )),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar registro')
                    ->requiresConfirmation()
                    ->modalDescription('Esto solo borra el registro — no elimina el archivo .txt del servidor.'),
            ])
            ->emptyStateHeading('Sin archivos generados')
            ->emptyStateDescription('Usa "Generar archivo BBVA" o "Generar archivo BCP" en la tabla de planillas (solo disponible con la planilla aprobada).');
    }
}
