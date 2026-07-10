<?php
namespace App\Filament\Resources\AttendanceRecordResource\Pages;
use App\Filament\Resources\AttendanceRecordResource;
use App\Helpers\CompanyContext;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendanceRecord extends CreateRecord
{
    protected static string $resource = AttendanceRecordResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = CompanyContext::get();
        return $data;
    }
}
