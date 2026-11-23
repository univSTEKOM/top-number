<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Leader\ContactController as LeaderContactController;
use App\Http\Controllers\Leader\ContactHistoryController as LeaderContactHistoryController;
use App\Http\Controllers\Leader\NumberRequestController as LeaderNumberRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubLeader\ContactController as SubLeaderContactController;
use App\Http\Controllers\SubLeader\ContactHistoryController as SubLeaderContactHistoryController;
use App\Http\Controllers\SuperAdmin\ContactController as SuperAdminContactController;
use App\Http\Controllers\SuperAdmin\ContactHistoryController as SuperAdminContactHistoryController;
use App\Http\Controllers\SuperAdmin\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:superadmin'])
    ->prefix('superadmin')
    ->name('superadmin.')
    ->group(function (): void {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/contacts', [SuperAdminContactController::class, 'index'])->name('contacts.index');
        Route::get('/history', [SuperAdminContactHistoryController::class, 'index'])->name('history.index');
        Route::get('/import', [UserManagementController::class, 'importForm'])->name('import');
        Route::post('/import', [UserManagementController::class, 'import'])->name('import.store');
        Route::post('/leaders', [UserManagementController::class, 'storeLeader'])->name('leaders.store');
        Route::post('/sub-leaders', [UserManagementController::class, 'storeSubLeader'])->name('sub-leaders.store');
        Route::post('/teams', [UserManagementController::class, 'storeTeam'])->name('teams.store');
        Route::patch('/users/{user}/assign-team', [UserManagementController::class, 'assignTeam'])
            ->name('users.assign-team');
        Route::patch('/users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])
            ->name('users.reset-password');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])
            ->name('users.destroy');
    });

Route::middleware(['auth', 'role:leader'])
    ->prefix('leader')
    ->name('leader.')
    ->group(function (): void {
        Route::get('/contacts', [LeaderContactController::class, 'index'])->name('contacts.index');
        Route::get('/contacts/export', [LeaderContactController::class, 'export'])->name('contacts.export');
        Route::get('/contacts/{contact}/whatsapp', [LeaderContactController::class, 'whatsapp'])->name('contacts.whatsapp');
        Route::patch('/contacts/{contact}/status', [LeaderContactController::class, 'updateStatus'])->name('contacts.status');
        Route::patch('/contacts/bulk-status', [LeaderContactController::class, 'bulkUpdateStatus'])->name('contacts.bulk-status');
        Route::get('/history', [LeaderContactHistoryController::class, 'index'])->name('history.index');
        Route::get('/requests', [LeaderNumberRequestController::class, 'index'])->name('requests.index');
        Route::post('/requests', [LeaderNumberRequestController::class, 'store'])->name('requests.store');
        Route::patch('/requests/{numberRequest}/approve', [LeaderNumberRequestController::class, 'approve'])->name('requests.approve');
        Route::patch('/requests/{numberRequest}/reject', [LeaderNumberRequestController::class, 'reject'])->name('requests.reject');
    });

Route::middleware(['auth', 'role:sub_leader'])
    ->prefix('sub-leader')
    ->name('subleader.')
    ->group(function (): void {
        Route::get('/contacts', [SubLeaderContactController::class, 'index'])->name('contacts.index');
        Route::post('/contacts', [SubLeaderContactController::class, 'store'])->name('contacts.store');
        Route::post('/contacts/import', [SubLeaderContactController::class, 'import'])->name('contacts.import');
        Route::get('/history', [SubLeaderContactHistoryController::class, 'index'])->name('history.index');
    });

require __DIR__.'/auth.php';
