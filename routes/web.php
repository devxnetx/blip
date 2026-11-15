<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing', [
        'appStoreUrl' => config('blip.app_store_url') ?: 'https://apps.apple.com',
        'playStoreUrl' => config('blip.play_store_url') ?: 'https://play.google.com/store',
    ]);
});

Route::view('/map', 'map')->name('map');
