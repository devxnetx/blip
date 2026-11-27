<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\PinController;
use App\Http\Controllers\Api\V1\PartnerController;
use Illuminate\Support\Facades\Route;

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/cities', [PinController::class, 'cities']);
Route::get('/pins', [PinController::class, 'index']);
Route::get('/pins/latest', [PinController::class, 'latest']);
Route::get('/pins/{pin}', [PinController::class, 'show']);
Route::post('/pins', [PinController::class, 'store'])->middleware('throttle:20,1');
Route::get('/pins/{pin}/replies', [PinController::class, 'replies']);
Route::post('/pins/{pin}/replies', [PinController::class, 'storeReply'])->middleware('throttle:20,1');
Route::post('/pins/{pin}/report', [PinController::class, 'report'])->middleware('throttle:30,1');
Route::delete('/pins/{pin}', [PinController::class, 'destroy']);

Route::middleware(['apikey', 'throttle:120,1'])->prefix('v1')->group(function () {
    Route::get('/cities', [PartnerController::class, 'cities']);
    Route::get('/types', [PartnerController::class, 'types']);
    Route::get('/times', [PartnerController::class, 'times']);
    Route::get('/events', [PartnerController::class, 'events']);
});
