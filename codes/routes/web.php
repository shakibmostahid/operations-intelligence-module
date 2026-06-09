<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::get('/change-password', [PasswordChangeController::class, 'edit'])
        ->name('password.change');
    Route::put('/change-password', [PasswordChangeController::class, 'update'])
        ->name('password.update');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('password.changed')->group(function (): void {
        Route::get('/dashboard', function () {
            $user = request()->user()->load('role');

            return view('app', [
                'page' => 'dashboard',
                'props' => [
                    'user' => [
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role->role,
                    ],
                    'success' => session('success'),
                ],
            ]);
        })->name('dashboard');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'updateName'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
            ->name('profile.password.update');

        Route::middleware('users.manage')->group(function (): void {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate'])
                ->name('users.deactivate');
            Route::patch('/users/{user}/reactivate', [UserController::class, 'reactivate'])
                ->name('users.reactivate');
        });
    });
});
