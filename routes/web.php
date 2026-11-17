<?php

use App\Http\Controllers\GeneratedDocumentController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\Master\DepartmentController;
use App\Http\Controllers\Master\UnitController;
use App\Http\Controllers\Master\VendorController;
use App\Http\Controllers\PrivateFileController;
use App\Http\Controllers\PurchaseRequestController;
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

    Route::get('purchase-requests', [PurchaseRequestController::class, 'index'])->name('purchase-requests.index');
    Route::get('purchase-requests/create', [PurchaseRequestController::class, 'create'])->name('purchase-requests.create');
    Route::post('purchase-requests', [PurchaseRequestController::class, 'store'])->name('purchase-requests.store');
    Route::get('purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'show'])->name('purchase-requests.show');
    Route::get('purchase-requests/{purchaseRequest}/edit', [PurchaseRequestController::class, 'edit'])->name('purchase-requests.edit');
    Route::put('purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'update'])->name('purchase-requests.update');
    Route::delete('purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'destroy'])->name('purchase-requests.destroy');
    Route::post('purchase-requests/{purchaseRequest}/finalize', [PurchaseRequestController::class, 'finalize'])->name('purchase-requests.finalize');
    Route::post('purchase-requests/{purchaseRequest}/revision', [PurchaseRequestController::class, 'revision'])->name('purchase-requests.revision');
    Route::post('purchase-requests/{purchaseRequest}/archive', [PurchaseRequestController::class, 'archive'])->name('purchase-requests.archive');
    Route::post('purchase-requests/{purchaseRequest}/documents', [PurchaseRequestController::class, 'generateDocument'])->name('purchase-requests.documents.store');
    Route::get('documents/{document}', GeneratedDocumentController::class)->name('documents.download');

    Route::get('/files/{path}', PrivateFileController::class)
        ->where('path', '.*')
        ->name('files.show');
});
