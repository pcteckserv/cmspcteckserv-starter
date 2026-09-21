<?php

use App\Http\Controllers\DeploymentPackageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('public.home');
})->name('home');

Route::get('/compilar', DeploymentPackageController::class)->name('deploy.compile');
