<?php

use App\Http\Controllers\BackupRunController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServerWizardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::resource('servers', ServerController::class)->except(['show']);
Route::get('servers/{server}', [ServerController::class, 'show'])->name('servers.show');
Route::get('servers/{server}/restore', [ServerController::class, 'restoreGuide'])->name('servers.restore');
Route::post('servers/{server}/setup', [ServerController::class, 'setup'])->name('servers.setup');
Route::post('servers/setup-all', [ServerController::class, 'setupAll'])->name('servers.setup-all');
Route::post('servers/{server}/backup', [ServerController::class, 'backup'])->name('servers.backup');
Route::post('servers/backup-all', [ServerController::class, 'backupAll'])->name('servers.backup-all');

Route::get('servers/{server}/wizard/step-1', [ServerWizardController::class, 'step1'])->name('servers.wizard.step1');
Route::post('servers/{server}/wizard/step-1', [ServerWizardController::class, 'updateStep1'])->name('servers.wizard.step1.update');
Route::get('servers/{server}/wizard/step-2', [ServerWizardController::class, 'step2'])->name('servers.wizard.step2');
Route::post('servers/{server}/wizard/test-connection', [ServerWizardController::class, 'testConnection'])->name('servers.wizard.test-connection');
Route::post('servers/{server}/wizard/discover-configs', [ServerWizardController::class, 'discoverConfigs'])->name('servers.wizard.discover-configs');
Route::post('servers/{server}/wizard/cancel-discovery', [ServerWizardController::class, 'cancelDiscovery'])->name('servers.wizard.cancel-discovery');
Route::get('servers/{server}/wizard/discovery-status', [ServerWizardController::class, 'discoveryStatus'])->name('servers.wizard.discovery-status');
Route::post('servers/{server}/wizard/step-3/go', [ServerWizardController::class, 'proceedToStep3'])->name('servers.wizard.step3.go');

Route::get('servers/{server}/wizard/step-3', [ServerWizardController::class, 'step3'])->name('servers.wizard.step3');
Route::post('servers/{server}/wizard/extract-databases', [ServerWizardController::class, 'extractDatabases'])->name('servers.wizard.extract-databases');
Route::post('servers/{server}/wizard/step-4/go', [ServerWizardController::class, 'proceedToStep4'])->name('servers.wizard.step4.go');

Route::get('servers/{server}/wizard/step-4', [ServerWizardController::class, 'step4'])->name('servers.wizard.step4');
Route::post('servers/{server}/wizard/step-4', [ServerWizardController::class, 'finishStep4'])->name('servers.wizard.step4.finish');

Route::get('projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
Route::post('projects/{project}/backup', [ProjectController::class, 'backup'])->name('projects.backup');

Route::get('backup-runs/{backupRun}', [BackupRunController::class, 'show'])->name('backup-runs.show');
Route::get('backup-runs/{backupRun}/status', [BackupRunController::class, 'status'])->name('backup-runs.status');
