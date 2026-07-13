<?php

use App\Http\Controllers\AdminActivityLogController;
use App\Http\Controllers\AdminDepartmentController;
use App\Http\Controllers\AdminMachineController;
use App\Http\Controllers\AdminMemberQcController;
use App\Http\Controllers\AdminNgTypeController;
use App\Http\Controllers\AdminProductController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\QcInspectionController;
use App\Http\Controllers\QcReportController;
use App\Http\Controllers\QcResultController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');
Route::get('/login', [AuthController::class, 'showLogin']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::middleware('role:admin,supervisor,qc_inspector')->group(function () {
    Route::get('/password', [PasswordController::class, 'edit']);
    Route::put('/password', [PasswordController::class, 'update']);
});

Route::prefix('admin')->middleware('role:admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'admin']);

    Route::get('/users', [AdminUserController::class, 'index']);
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::put('/users/{id}', [AdminUserController::class, 'update']);
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);
    Route::put('/users/{id}/reset-password', [AdminUserController::class, 'resetPassword']);

    Route::get('/machines', [AdminMachineController::class, 'index']);
    Route::post('/machines', [AdminMachineController::class, 'store']);
    Route::put('/machines/{id}', [AdminMachineController::class, 'update']);
    Route::put('/machines/{id}/reset-password', [AdminMachineController::class, 'resetPassword']);
    Route::delete('/machines/{id}', [AdminMachineController::class, 'destroy']);

    Route::get('/departments', [AdminDepartmentController::class, 'index']);
    Route::post('/departments', [AdminDepartmentController::class, 'store']);
    Route::put('/departments/{id}', [AdminDepartmentController::class, 'update']);
    Route::delete('/departments/{id}', [AdminDepartmentController::class, 'destroy']);

    Route::get('/products', [AdminProductController::class, 'index']);
    Route::post('/products', [AdminProductController::class, 'store']);
    Route::put('/products/{id}', [AdminProductController::class, 'update']);
    Route::delete('/products/{id}', [AdminProductController::class, 'destroy']);

    Route::get('/ng-types', [AdminNgTypeController::class, 'index']);
    Route::post('/ng-types', [AdminNgTypeController::class, 'store']);
    Route::put('/ng-types/{id}', [AdminNgTypeController::class, 'update']);
    Route::delete('/ng-types/{id}', [AdminNgTypeController::class, 'destroy']);

    Route::get('/members-qc', [AdminMemberQcController::class, 'index']);
    Route::post('/members-qc', [AdminMemberQcController::class, 'store']);
    Route::put('/members-qc/{id}', [AdminMemberQcController::class, 'update']);
    Route::delete('/members-qc/{id}', [AdminMemberQcController::class, 'destroy']);

    Route::get('/activity-logs', [AdminActivityLogController::class, 'index']);
});

Route::prefix('qc')->middleware('role:qc_inspector')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'qc']);
    Route::get('/input', [QcInspectionController::class, 'createWeb']);
    Route::post('/input', [QcInspectionController::class, 'storeWeb']);
    Route::put('/input/{inspection}/resolve', [QcInspectionController::class, 'resolvePendingWeb']);
    Route::redirect('/machines', '/qc/results');
    Route::get('/results', [QcResultController::class, 'index']);
    Route::get('/report', [QcReportController::class, 'index'])->defaults('role', 'qc');
    Route::get('/report/pdf', [QcReportController::class, 'exportPdf'])->defaults('role', 'qc');
    Route::get('/report/excel', [QcReportController::class, 'exportExcel']);
});

Route::prefix('supervisor')->middleware('role:supervisor')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'supervisor']);
    Route::redirect('/machines', '/supervisor/results');
    Route::get('/results', [QcResultController::class, 'index'])->defaults('role', 'supervisor');
    Route::get('/report', [QcReportController::class, 'index'])->defaults('role', 'supervisor');
    Route::get('/report/pdf', [QcReportController::class, 'exportPdf'])->defaults('role', 'supervisor');
    Route::get('/report/excel', [QcReportController::class, 'exportExcel']);
});

Route::prefix('mesin')->middleware('role:akun_mesin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'mesin']);
    Route::get('/results', [QcResultController::class, 'index'])->defaults('role', 'mesin');
});
