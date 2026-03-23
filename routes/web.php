<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

require __DIR__.'/checkin.php';

// Selección de empresa
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/select-company', [App\Http\Controllers\CompanySelectController::class, 'show'])->name('company.show');
    Route::post('/select-company', [App\Http\Controllers\CompanySelectController::class, 'select'])->name('company.select');
});
