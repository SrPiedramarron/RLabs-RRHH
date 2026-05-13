<?php
use App\Http\Controllers\ZKTecoADMSController;
use Illuminate\Support\Facades\Route;

Route::prefix('iclock')->group(function () {
    Route::get('cdata',     [ZKTecoADMSController::class, 'handshake']);
    Route::post('cdata',    [ZKTecoADMSController::class, 'receiveData']);
    Route::get('getrequest',[ZKTecoADMSController::class, 'getRequest']);
    Route::post('devicecmd',[ZKTecoADMSController::class, 'deviceCmd']);
});
