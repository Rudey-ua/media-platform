<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\PlayerController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::middleware(['auth', 'role:admin'])->group(function (): void {
    Route::get('/', [PlayerController::class, 'index'])->name('player.home');
    Route::get('/video-upload', [PlayerController::class, 'upload'])->name('player.upload');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
