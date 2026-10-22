<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::middleware('guest')->group(function () {
    Route::view('/entrar', 'auth.login')->name('login');
    Route::post('/entrar', [AuthController::class, 'login'])->name('login.store');
    Route::view('/criar-conta', 'auth.register')->name('register');
    Route::post('/criar-conta', [AuthController::class, 'register'])->middleware('throttle:6,1')->name('register.store');
    Route::view('/esqueci-senha', 'auth.forgot-password')->name('password.request');
    Route::post('/esqueci-senha', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/redefinir-senha/{token}', fn (string $token) => view('auth.reset-password', ['token' => $token, 'email' => request('email')]))->name('password.reset');
    Route::post('/redefinir-senha', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/sair', [AuthController::class, 'logout'])->name('logout');
    Route::get('/painel', [WorkspaceController::class, 'dashboard'])->name('dashboard');
    Route::view('/dashboard-financeira', 'financial-dashboard')->name('financial.dashboard');
    Route::get('/cadastros/{module}', [WorkspaceController::class, 'catalog'])->whereIn('module', ['categorias', 'contatos', 'contas', 'formas'])->name('catalog');
    Route::get('/receitas', [WorkspaceController::class, 'titles'])->defaults('type', 'receivable')->name('income');
    Route::get('/despesas', [WorkspaceController::class, 'titles'])->defaults('type', 'payable')->name('expenses');
    Route::get('/titulos/{title}', [WorkspaceController::class, 'title'])->name('titles.show');
    Route::get('/recibos', [WorkspaceController::class, 'receipts'])->name('receipts.index');
    Route::get('/notificacoes', [WorkspaceController::class, 'notifications'])->name('notifications.index');
    Route::get('/preferencias', [WorkspaceController::class, 'preferences'])->name('preferences');
    Route::post('/notificacoes/ler-todas', [WorkspaceController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notificacoes/atualizar', [WorkspaceController::class, 'refreshNotifications'])->name('notifications.refresh');
    Route::post('/notificacoes/{notification}/lida', [WorkspaceController::class, 'read'])->name('notifications.read-web');
    Route::post('/baixas/{settlement}/recibo', [ReceiptController::class, 'store'])->name('receipts.store');
    Route::get('/recibos/{receipt}/download', [ReceiptController::class, 'download'])->name('receipts.download');
    Route::patch('/notificacoes/{notification}/ler', [NotificationController::class, 'read'])->name('notifications.read');
});
