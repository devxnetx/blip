<?php

use App\Http\Controllers\Api\PinController;
use Illuminate\Support\Facades\Route;

Route::get('/pins', [PinController::class, 'index']);
Route::get('/pins/{pin}', [PinController::class, 'show']);
Route::post('/pins', [PinController::class, 'store'])->middleware('throttle:20,1');
Route::get('/pins/{pin}/replies', [PinController::class, 'replies']);
Route::post('/pins/{pin}/replies', [PinController::class, 'storeReply'])->middleware('throttle:20,1');
Route::post('/pins/{pin}/report', [PinController::class, 'report'])->middleware('throttle:30,1');
Route::delete('/pins/{pin}', [PinController::class, 'destroy']);
