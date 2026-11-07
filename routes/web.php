<?php

use App\Http\Controllers\CalculatorController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CalculatorController::class, 'index'])->name('calculator');

Route::middleware('throttle:120,1')->group(function () {
    Route::post('/calculate', [CalculatorController::class, 'calculate'])->name('calculator.calculate');
    Route::get('/history', [CalculatorController::class, 'history'])->name('calculator.history');
    Route::delete('/history', [CalculatorController::class, 'clearHistory'])->name('calculator.history.clear');
});

// Alamat lama tetap bisa dibuka.
Route::redirect('/kalkulator.php', '/', 301);
