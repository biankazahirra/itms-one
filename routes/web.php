<?php

use App\Http\Controllers\LoginController;
use App\Http\Controllers\Master\DepartmentController;
use App\Http\Controllers\Master\UnitController;
use App\Http\Controllers\Master\VendorController;
use App\Http\Controllers\PrivateFileController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => view('dashboard'))->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::prefix('master')->name('master.')->group(function () {
        Route::resource('vendors', VendorController::class)->except(['show', 'destroy']);
        Route::resource('units', UnitController::class)->except(['show', 'destroy']);
        Route::resource('departments', DepartmentController::class)->except(['show', 'destroy']);
    });

    Route::get('/files/{path}', PrivateFileController::class)
        ->where('path', '.*')
        ->name('files.show');
});
