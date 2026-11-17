<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BeneficiaryController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\TechnicalAidController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Login
Route::post('/login', [AuthController::class, 'login'])
    ->name('login');

// Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {

    // Autenticación
    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('logout');

    Route::get('/user', function (Request $request) {
        return $request->user();
    })->name('user');

    // Crear usuarios gestores
    Route::post('/users', [AuthController::class, 'createManager'])
        ->name('users.store');

    // Gestión de beneficiarios: administrador y gestor
    Route::apiResource('beneficiaries', BeneficiaryController::class)
        ->middleware('role:admin,manager');

    // Gestión de ayudas técnicas
    Route::apiResource('technical-aids', TechnicalAidController::class)
        ->parameters([
            'technical-aids' => 'technicalAid',
        ]);

    // Consultar préstamos vencidos
    Route::get('/loans/overdue', [LoanController::class, 'overdue'])
        ->name('loans.overdue');

    // Gestión de préstamos
    Route::apiResource('loans', LoanController::class)
        ->only(['index', 'store', 'show']);

    // Devolver elementos de un préstamo
    Route::post('/loans/{loan}/items/{item}/return', [LoanController::class, 'returnLine'])
        ->name('loans.items.return');
});
