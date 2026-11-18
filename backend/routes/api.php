<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalculadoraController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ComparisonController;
use App\Http\Controllers\Api\InvoiceImportController;
use App\Http\Controllers\Api\OfficeController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware(['auth:sanctum', 'tenant', 'throttle:api'])->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('office', [OfficeController::class, 'show']);
    Route::put('office', [OfficeController::class, 'update']);
    Route::post('office/logo', [OfficeController::class, 'uploadLogo']);

    Route::apiResource('users', UserController::class)->except('show');
    Route::apiResource('clients', ClientController::class);

    // Invoices (XML NF-e/NFC-e/NFS-e)
    Route::get('clients/{client}/imports', [InvoiceImportController::class, 'index']);
    Route::post('clients/{client}/imports', [InvoiceImportController::class, 'store'])->middleware('subscription');
    Route::get('imports/{import}', [InvoiceImportController::class, 'show']);
    Route::get('clients/{client}/invoice-items', [InvoiceImportController::class, 'items']);
    Route::delete('clients/{client}/invoice-items', [InvoiceImportController::class, 'destroyItems']);

    // Comparisons (Simples integral x IBS/CBS regular)
    Route::get('comparisons', [ComparisonController::class, 'index']);
    Route::post('clients/{client}/comparisons', [ComparisonController::class, 'store'])->middleware('subscription');
    Route::get('comparisons/{comparison}', [ComparisonController::class, 'show']);
    Route::get('comparisons/{comparison}/audit/{scenario}', [ComparisonController::class, 'audit']);
    Route::post('comparisons/{comparison}/retry', [ComparisonController::class, 'retry'])->middleware('subscription');
    Route::delete('comparisons/{comparison}', [ComparisonController::class, 'destroy']);

    Route::prefix('calculadoras')->group(function () {
        Route::get('status', [CalculadoraController::class, 'status']);
        Route::get('atividades', [CalculadoraController::class, 'atividades']);
        Route::get('ncm', [CalculadoraController::class, 'ncm']);
        Route::get('nbs', [CalculadoraController::class, 'nbs']);
        Route::get('classificacoes/{cClassTrib}', [CalculadoraController::class, 'classificacao']);
    });
});
