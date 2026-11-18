<?php

use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ContractController;
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
        Route::post('units/quick-add', [UnitController::class, 'quickAdd'])->name('units.quick-add');
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
    Route::get('rab', [BudgetController::class, 'index'])->name('rab.index');
    Route::get('rab/create', [BudgetController::class, 'create'])->name('rab.create');
    Route::post('rab', [BudgetController::class, 'store'])->name('rab.store');
    Route::get('rab/{budget}', [BudgetController::class, 'show'])->name('rab.show');
    Route::get('rab/{budget}/edit', [BudgetController::class, 'edit'])->name('rab.edit');
    Route::put('rab/{budget}', [BudgetController::class, 'update'])->name('rab.update');
    Route::delete('rab/{budget}', [BudgetController::class, 'destroy'])->name('rab.destroy');
    Route::post('rab/{budget}/finalize', [BudgetController::class, 'finalize'])->name('rab.finalize');
    Route::post('rab/{budget}/revision', [BudgetController::class, 'revision'])->name('rab.revision');
    Route::post('rab/{budget}/archive', [BudgetController::class, 'archive'])->name('rab.archive');
    Route::post('rab/{budget}/documents', [BudgetController::class, 'generateDocument'])->name('rab.documents.store');

    Route::get('contracts', [ContractController::class, 'index'])->name('contracts.index');
    Route::get('contracts/create', [ContractController::class, 'create'])->name('contracts.create');
    Route::post('contracts', [ContractController::class, 'store'])->name('contracts.store');
    Route::get('contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show');
    Route::get('contracts/{contract}/edit', [ContractController::class, 'edit'])->name('contracts.edit');
    Route::put('contracts/{contract}', [ContractController::class, 'update'])->name('contracts.update');
    Route::delete('contracts/{contract}', [ContractController::class, 'destroy'])->name('contracts.destroy');
    Route::post('contracts/{contract}/archive', [ContractController::class, 'archive'])->name('contracts.archive');
    Route::post('contracts/{contract}/unarchive', [ContractController::class, 'unarchive'])->name('contracts.unarchive');
    Route::post('contracts/{contract}/attachments', [ContractController::class, 'storeAttachments'])->name('contracts.attachments.store');
    Route::delete('contracts/{contract}/attachments/{attachment}', [ContractController::class, 'destroyAttachment'])->name('contracts.attachments.destroy');
    Route::get('contracts/{contract}/attachments/{attachment}', [ContractController::class, 'downloadAttachment'])->name('contracts.attachments.download');

    Route::get('documents/{document}', GeneratedDocumentController::class)->name('documents.download');

    Route::get('/files/{path}', PrivateFileController::class)
        ->where('path', '.*')
        ->name('files.show');
});
