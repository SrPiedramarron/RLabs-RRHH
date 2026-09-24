<?php

namespace App\Filament\Resources\ContractRenewalResource\Pages;

use App\Filament\Resources\ContractRenewalResource;
use App\Helpers\CompanyContext;
use App\Models\ContractRenewal;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListContractRenewals extends ListRecords
{
    protected static string $resource = ContractRenewalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportar')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $records = ContractRenewal::query()
                        ->with(['employee', 'renovadoPor'])
                        ->when(CompanyContext::get(), fn ($q, $companyId) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $companyId)))
                        ->orderByDesc('renovado_at')
                        ->get();

                    return Excel::download(
                        new \App\Exports\RenovacionesContratoExport($records),
                        'renovaciones_contrato_' . now()->format('Y-m-d') . '.xlsx'
                    );
                }),
        ];
    }
}
