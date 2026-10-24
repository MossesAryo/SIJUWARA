<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\catatanController;
use App\Http\Controllers\AkumulasiController;
use App\Http\Controllers\api\NotifikasiController;
use App\Http\Controllers\PenghargaanController;
use App\Http\Controllers\api\skoringpelanggaran;
use App\Http\Controllers\api\SkoringPenghargaan;
use App\Http\Controllers\Aspek_penilaianController;
use App\Http\Controllers\AuthAPI\AuthAPIcontroller;
use App\Http\Controllers\SuratPeringatanController;
use App\Http\Controllers\IntervensiController;



Route::post('/login',  [AuthAPIcontroller::class, 'login']);



Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthAPIcontroller::class, 'logout']);

    Route::get('/kelas',          [KelasController::class,           'FetchApi'])->name('api.kelas');
    Route::get('/aspekpenilaian', [Aspek_penilaianController::class, 'FetchApi'])->name('api.aspek');
    Route::get('/Penghargaan',    [PenghargaanController::class,     'FetchApi'])->name('api.penghargaan');
    Route::get('/peringatan',     [SuratPeringatanController::class, 'FetchApi'])->name('api.peringatan');
    Route::get('/siswa',          [SiswaController::class,           'FetchApi'])->name('api.siswa');
    Route::get('/akumulasi',      [AkumulasiController::class,        'fetchAPI']);
    Route::get('/notifikasi',     [NotifikasiController::class,      'index']);

    Route::get('/skoring_penghargaan',  [SkoringPenghargaan::class,  'index']);
    Route::get('/skoring_pelanggaran', [skoringpelanggaran::class,   'index']);    
    Route::post('/skoring_penghargaan', [SkoringPenghargaan::class,  'store']);
    Route::post('/skoring_pelanggaran', [skoringpelanggaran::class,   'store']);
    Route::post('/AddCatatan/{nis}', [catatanController::class, 'AddCatatanAPI']);
    Route::post('/addpenanganan/{nis}', [IntervensiController::class, 'AddPenangananAPI']);
    Route::get('/penanganan/{nis}', [IntervensiController::class, 'GetPenangananAPI']);
    Route::put('/penanganan/{id}', [IntervensiController::class, 'UpdatePenangananAPI']);
    Route::delete('/penanganan/{id}', [IntervensiController::class, 'DeletePenangananAPI']);

});