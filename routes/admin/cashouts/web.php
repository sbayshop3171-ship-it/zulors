<?php

use Illuminate\Support\Facades\Route;

Route::get('/', [App\Http\Controllers\Admin\Cashout\CashoutController::class, 'index'])->name('admin.cashouts.index');

Route::middleware('sided.layout')->get('/{cashoutId}/show', [App\Http\Controllers\Admin\Cashout\CashoutController::class, 'show'])->name('admin.cashouts.show');

Route::post('/delete/{cashoutId}', [App\Http\Controllers\Admin\Cashout\CashoutController::class, 'destroy'])->name('admin.cashouts.destroy');

Route::post('/reject/{cashoutId}', [App\Http\Controllers\Admin\Cashout\CashoutController::class, 'reject'])->name('admin.cashouts.reject');

Route::post('/mark-as-paid/{cashoutId}', [App\Http\Controllers\Admin\Cashout\CashoutController::class, 'markAsPaid'])->name('admin.cashouts.mark_as_paid');
