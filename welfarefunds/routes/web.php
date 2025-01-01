<?php

use App\Http\Controllers\Auth\LoginController;  
use App\Http\Controllers\DashboardController;  
use Illuminate\Support\Facades\Route;  

Route::middleware('guest')->group(function () {  
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');  
    Route::post('login', [LoginController::class, 'login']);  
});  

Route::middleware('auth')->group(function () {  
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');  
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');  
});  

Route::redirect('/', '/dashboard');  