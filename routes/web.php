<?php

use App\Http\Controllers\DeploymentPackageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('public.home');
})->name('home');

Route::get('/compilar', [DeploymentPackageController::class, 'index'])
    ->middleware(['auth', \App\Http\Middleware\EnsureDeploymentAccess::class])
    ->name('deploy.compile');

Route::post('/compilar', [DeploymentPackageController::class, 'store'])
    ->middleware(['auth', \App\Http\Middleware\EnsureDeploymentAccess::class, 'throttle:3,1'])
    ->name('deploy.compile.store');
