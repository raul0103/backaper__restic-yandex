<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServerWizardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/guide', [GuideController::class, 'index'])->name('guide');

Route::resource('servers', ServerController::class)->except(['show']);
Route::get('servers/{server}', [ServerController::class, 'show'])->name('servers.show');
Route::get('servers/{server}/restore', [ServerController::class, 'restoreGuide'])->name('servers.restore');
Route::post('servers/{server}/setup', [ServerController::class, 'setup'])->name('servers.setup');

Route::get('servers/{server}/wizard/connect', [ServerWizardController::class, 'connect'])->name('servers.wizard.connect');
Route::post('servers/{server}/wizard/connect', [ServerWizardController::class, 'updateConnect'])->name('servers.wizard.connect.update');
Route::get('servers/{server}/wizard/install', [ServerWizardController::class, 'install'])->name('servers.wizard.install');
Route::post('servers/{server}/wizard/install', [ServerWizardController::class, 'runInstall'])->name('servers.wizard.install.run');
Route::post('servers/{server}/wizard/test-connection', [ServerWizardController::class, 'testConnection'])->name('servers.wizard.test-connection');
