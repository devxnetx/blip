<?php

use App\Http\Controllers\Admin\CategoryController;
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
    Route::get('/admin/devices/{device}/edit', [DeviceController::class, 'edit'])->name('admin.devices.edit');
    Route::put('/admin/devices/{device}', [DeviceController::class, 'update'])->name('admin.devices.update');
    Route::get('/admin/devices/{device}', [DeviceController::class, 'show'])->name('admin.devices.show');
    Route::post('/admin/devices/{device}/ban', [DeviceController::class, 'ban'])->name('admin.devices.ban');
    Route::post('/admin/devices/{device}/unban', [DeviceController::class, 'unban'])->name('admin.devices.unban');
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users');
    Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::get('/admin/users/{user}', [UserController::class, 'show'])->name('admin.users.show');
    Route::get('/admin/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
    Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    Route::get('/admin/categories', [CategoryController::class, 'index'])->name('admin.categories');
    Route::post('/admin/categories', [CategoryController::class, 'store'])->name('admin.categories.store');
    Route::get('/admin/categories/{category}/edit', [CategoryController::class, 'edit'])->name('admin.categories.edit');
    Route::put('/admin/categories/{category}', [CategoryController::class, 'update'])->name('admin.categories.update');
    Route::get('/admin/categories/{category}', [CategoryController::class, 'show'])->name('admin.categories.show');
    Route::delete('/admin/categories/{category}', [CategoryController::class, 'destroy'])->name('admin.categories.destroy');
    Route::get('/admin/pins', [AdminPinController::class, 'index'])->name('admin.pins');
    Route::post('/admin/pins', [AdminPinController::class, 'store'])->name('admin.pins.store');
    Route::get('/admin/pins/unassigned', [AdminPinController::class, 'unassigned'])->name('admin.pins.unassigned');
    Route::post('/admin/pins/unassigned', [AdminPinController::class, 'storeUnassigned'])->name('admin.pins.unassigned.store');
    Route::post('/admin/pins/{pin}/place', [AdminPinController::class, 'place'])->name('admin.pins.place');
    Route::post('/admin/pins/{pin}/hide', [AdminPinController::class, 'hide'])->name('admin.pins.hide');
    Route::post('/admin/pins/{pin}/ban', [AdminPinController::class, 'ban'])->name('admin.pins.ban');
});
