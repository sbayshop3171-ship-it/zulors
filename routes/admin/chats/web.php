<?php

use Illuminate\Support\Facades\Route;

Route::get('/', [App\Http\Controllers\Admin\Chat\ChatController::class, 'index'])->name('admin.chats.index');

Route::get('/{chatId}/show', [App\Http\Controllers\Admin\Chat\ChatController::class, 'show'])->name('admin.chats.show')
    ->where('chatId', '[0-9]+');

Route::post('/{chatId}/delete', [App\Http\Controllers\Admin\Chat\ChatController::class, 'destroy'])->name('admin.chats.destroy')
    ->where('chatId', '[0-9]+');
