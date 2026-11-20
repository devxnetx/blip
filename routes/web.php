<?php

use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\PinController as AdminPinController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing', [
        'appStoreUrl' => config('blip.app_store_url'),
        'playStoreUrl' => config('blip.play_store_url'),
    ]);
});

Route::view('/map', 'map')->name('map');
Route::view('/testmap', 'testmap');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [SessionController::class, 'create'])->name('admin.login');
    Route::post('/admin/login', [SessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('admin.login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/admin/logout', [SessionController::class, 'destroy'])->name('admin.logout');
    Route::redirect('/admin', '/admin/devices');
    Route::get('/admin/devices', [DeviceController::class, 'index'])->name('admin.devices');
    Route::get('/admin/devices/{device}', [DeviceController::class, 'show'])->name('admin.devices.show');
    Route::post('/admin/devices/{device}/ban', [DeviceController::class, 'ban'])->name('admin.devices.ban');
    Route::post('/admin/devices/{device}/unban', [DeviceController::class, 'unban'])->name('admin.devices.unban');
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users');
    Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    Route::get('/admin/pins', [AdminPinController::class, 'index'])->name('admin.pins');
    Route::post('/admin/pins', [AdminPinController::class, 'store'])->name('admin.pins.store');
    Route::post('/admin/pins/{pin}/ban', [AdminPinController::class, 'ban'])->name('admin.pins.ban');
});
