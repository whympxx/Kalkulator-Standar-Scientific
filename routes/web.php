<?php

use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\CryptoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CalculatorController::class, 'index'])->name('calculator');

Route::middleware('throttle:120,1')->group(function () {
    Route::post('/calculate', [CalculatorController::class, 'calculate'])->name('calculator.calculate');
    Route::get('/history', [CalculatorController::class, 'history'])->name('calculator.history');
    Route::get('/history/export', [CalculatorController::class, 'exportHistory'])->name('calculator.history.export');
    Route::post('/plot', [CalculatorController::class, 'plot'])->name('calculator.plot');
    Route::delete('/history', [CalculatorController::class, 'clearHistory'])->name('calculator.history.clear');

    Route::prefix('crypto')->name('crypto.')->controller(CryptoController::class)->group(function () {
        Route::post('/modular', 'modular')->name('modular');
        Route::post('/rsa', 'rsa')->name('rsa');
        Route::post('/cipher', 'cipher')->name('cipher');
        Route::post('/hash', 'hash')->name('hash');
        Route::post('/base', 'base')->name('base');
        Route::post('/xor', 'xor')->name('xor');
    });
});

// Pratinjau dipanggil saat mengetik, jadi batasnya lebih longgar.
Route::post('/preview', [CalculatorController::class, 'preview'])
    ->middleware('throttle:600,1')
    ->name('calculator.preview');

// Alamat lama tetap bisa dibuka.
Route::redirect('/kalkulator.php', '/', 301);
