<?php

use App\Http\Controllers\CollectionProofController;
use App\Http\Controllers\PlatformTenantManagementController;
use App\Http\Controllers\TenantSwitchController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\VerificationPrintController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : view('welcome'))->name('home');

Route::post('/tenant', TenantSwitchController::class)
    ->middleware('auth')
    ->name('tenant.switch');

Route::post('/platform/empresas/{tenant}/gestionar', [PlatformTenantManagementController::class, 'manage'])
    ->middleware('auth')
    ->name('platform.tenants.manage');

Route::post('/platform/suplantacion/salir', [PlatformTenantManagementController::class, 'exit'])
    ->middleware('auth')
    ->name('platform.impersonation.exit');

Route::livewire('/cartera', 'pages::portfolio')
    ->middleware('auth')
    ->name('portfolio');

Route::livewire('/cobros', 'pages::receipts')
    ->middleware('auth')
    ->name('receipts');

Route::livewire('/cobranzas', 'pages::collections')
    ->middleware('auth')
    ->name('collections.index');

Route::livewire('/cobranzas/registrar', 'pages::collection-create')
    ->middleware('auth')
    ->name('collections.create');

Route::livewire('/arqueos', 'pages::cash-sessions')
    ->middleware('auth')
    ->name('cash-sessions.index');

Route::livewire('/arqueos/{cashSession}', 'pages::cash-session')
    ->middleware('auth')
    ->name('cash-sessions.show');

Route::livewire('/cartera/vendedores', 'pages::portfolio-by-rep')
    ->middleware('auth')
    ->name('portfolio.by-rep');

Route::livewire('/admin/asignaciones', 'pages::admin.assignments')
    ->middleware(['auth', 'can:assignments.manage'])
    ->name('admin.assignments');

Route::livewire('/admin/auditoria', 'pages::admin.audit')
    ->middleware(['auth', 'can:viewAny,App\Models\AuditEvent'])
    ->name('admin.audit');

Route::livewire('/admin/comprobantes', 'pages::admin.verification-codes')
    ->middleware(['auth', 'can:viewAny,App\Models\VerificationCode'])
    ->name('admin.verification-codes');

Route::livewire('/dashboard', 'pages::dashboard')
    ->middleware('auth')
    ->name('dashboard');

Route::livewire('/user/profile', 'pages::profile')
    ->middleware('auth')
    ->name('profile');

Route::livewire('/admin/usuarios', 'pages::admin.users')
    ->middleware(['auth', 'can:users.manage'])
    ->name('admin.users');

Route::livewire('/admin/empresas', 'pages::admin.tenants')
    ->middleware(['auth', 'can:viewAny,App\Models\Tenant'])
    ->name('admin.tenants');

Route::get('/cobranzas/{collectionEvent}/comprobante', CollectionProofController::class)
    ->middleware('auth')
    ->name('collections.proof');

Route::get('/verificar/{code}', VerificationController::class)
    ->where('code', '[A-Za-z0-9]+')
    ->middleware('throttle:30,1')
    ->name('verification.show');

Route::get('/verificar/{code}/comprobante', VerificationPrintController::class)
    ->where('code', '[A-Za-z0-9]+')
    ->middleware(['auth', 'throttle:30,1'])
    ->name('verification.print');
