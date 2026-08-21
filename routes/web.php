<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ComisionesExportController;
use App\Http\Controllers\PlanillaExportController;

Route::get('/', fn() => redirect('/admin'));

require __DIR__.'/checkin.php';

// Selección de empresa
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/select-company', [App\Http\Controllers\CompanySelectController::class, 'show'])->name('company.show');
    Route::post('/select-company', [App\Http\Controllers\CompanySelectController::class, 'select'])->name('company.select');
    Route::get('/comisiones/{upload}/exportar', [ComisionesExportController::class, 'exportar'])
    ->name('comisiones.exportar')
    ->middleware(['auth']);
Route::get('/planilla/exportar', [PlanillaExportController::class, 'exportar'])
    ->name('planilla.exportar')
    ->middleware(['auth']);
Route::get('/boletas/exportar/{periodo}/{companyId}', [\App\Http\Controllers\BoletaExportController::class, 'exportarExcel'])
    ->name('boletas.exportar');

Route::get('/boletas/pdf/{liquidacion}', [\App\Http\Controllers\BoletaExportController::class, 'exportarPdf'])
    ->name('boletas.pdf');


});
require __DIR__.'/adms.php';
