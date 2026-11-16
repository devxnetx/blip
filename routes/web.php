<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing', [
        'appStoreUrl' => config('blip.app_store_url'),
        'playStoreUrl' => config('blip.play_store_url'),
    ]);
});

Route::view('/map', 'map')->name('map');
Route::view('/testmap', 'testmap');
