<?php

use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ChangelogController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\PinController as AdminPinController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\PinPageController;
use App\Http\Controllers\SiteController;
use App\Models\Pin;
use App\Support\LinkPreview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing', [
        'appStoreUrl' => config('blip.app_store_url'),
        'playStoreUrl' => config('blip.play_store_url'),
        'preview' => LinkPreview::page(
            'Kostaa — бележка, която изчезва',
            'Остави кратка бележка там, където стоиш. Добави снимка, ако искаш. Стои на сива карта, докато таймерът изтече.',
            rtrim(request()->getSchemeAndHttpHost(), '/').'/',
        ),
    ]);
});

Route::get('/changelog', [SiteController::class, 'changelog'])->name('changelog');
Route::redirect('/about', '/changelog', 301);
Route::get('/how', [SiteController::class, 'how'])->name('how');
Route::get('/contact', [SiteController::class, 'contact'])->name('contact');
Route::post('/contact', [SiteController::class, 'storeContact'])
    ->middleware('throttle:5,1')
    ->name('contact.store');
Route::get('/support', [SiteController::class, 'support'])->name('support');
Route::redirect('/history', '/media', 301);
Route::get('/media', [SiteController::class, 'media'])->name('media');
Route::get('/sitemap', [SiteController::class, 'karta'])->name('karta');
Route::get('/api', [SiteController::class, 'api'])->name('api.docs');
Route::get('/pins', [PinPageController::class, 'index'])->name('pins');
Route::get('/pins/{city}', [PinPageController::class, 'city'])->where('city', '[a-z0-9-]+')->name('pins.city');
Route::get('/pins/{city}/{pin}', [PinPageController::class, 'show'])->where(['city' => '[a-z0-9-]+', 'pin' => '[a-z0-9-]+'])->name('pins.show');
Route::get('/sitemap.xml', [PinPageController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [PinPageController::class, 'robots'])->name('robots');

Route::get('/map', function (Request $request) {
    $pin = null;
    $raw = $request->query('pin');
    if (is_string($raw) && ctype_digit($raw)) {
        $pin = Pin::query()->find((int) $raw);
    }

    return view('map', [
        'preview' => LinkPreview::forPin($pin),
    ]);
})->name('map');
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
    Route::get('/admin/changelog', [ChangelogController::class, 'index'])->name('admin.changelog');
    Route::get('/admin/changelog/create', [ChangelogController::class, 'create'])->name('admin.changelog.create');
    Route::post('/admin/changelog/preview', [ChangelogController::class, 'preview'])->name('admin.changelog.preview');
    Route::post('/admin/changelog', [ChangelogController::class, 'store'])->name('admin.changelog.store');
    Route::get('/admin/changelog/{entry}/edit', [ChangelogController::class, 'edit'])->name('admin.changelog.edit');
    Route::put('/admin/changelog/{entry}', [ChangelogController::class, 'update'])->name('admin.changelog.update');
    Route::delete('/admin/changelog/{entry}', [ChangelogController::class, 'destroy'])->name('admin.changelog.destroy');
    Route::get('/admin/contacts', [ContactMessageController::class, 'index'])->name('admin.contacts');
    Route::get('/admin/contacts/{contactMessage}', [ContactMessageController::class, 'show'])->name('admin.contacts.show');
    Route::delete('/admin/contacts/{contactMessage}', [ContactMessageController::class, 'destroy'])->name('admin.contacts.destroy');
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users');
    Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::get('/admin/users/{user}', [UserController::class, 'show'])->name('admin.users.show');
    Route::get('/admin/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
    Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    Route::get('/admin/api-keys', [ApiKeyController::class, 'index'])->name('admin.api-keys');
    Route::post('/admin/api-keys', [ApiKeyController::class, 'store'])->name('admin.api-keys.store');
    Route::post('/admin/api-keys/{apiKey}/revoke', [ApiKeyController::class, 'revoke'])->name('admin.api-keys.revoke');
    Route::get('/admin/settings', [SettingsController::class, 'edit'])->name('admin.settings');
    Route::put('/admin/settings', [SettingsController::class, 'update'])->name('admin.settings.update');
    Route::get('/admin/categories', [CategoryController::class, 'index'])->name('admin.categories');
    Route::post('/admin/categories', [CategoryController::class, 'store'])->name('admin.categories.store');
    Route::get('/admin/categories/{category}/edit', [CategoryController::class, 'edit'])->name('admin.categories.edit');
    Route::put('/admin/categories/{category}', [CategoryController::class, 'update'])->name('admin.categories.update');
    Route::get('/admin/categories/{category}', [CategoryController::class, 'show'])->name('admin.categories.show');
    Route::delete('/admin/categories/{category}', [CategoryController::class, 'destroy'])->name('admin.categories.destroy');
    Route::get('/admin/imports', [AdminPinController::class, 'imports'])->name('admin.imports');
    Route::get('/admin/pins', [AdminPinController::class, 'index'])->name('admin.pins');
    Route::post('/admin/pins', [AdminPinController::class, 'store'])->name('admin.pins.store');
    Route::get('/admin/pins/unassigned', [AdminPinController::class, 'unassigned'])->name('admin.pins.unassigned');
    Route::post('/admin/pins/unassigned', [AdminPinController::class, 'storeUnassigned'])->name('admin.pins.unassigned.store');
    Route::get('/admin/pins/{pin}', [AdminPinController::class, 'show'])->whereNumber('pin')->name('admin.pins.show');
    Route::post('/admin/pins/{pin}/place', [AdminPinController::class, 'place'])->name('admin.pins.place');
    Route::post('/admin/pins/{pin}/hide', [AdminPinController::class, 'hide'])->name('admin.pins.hide');
    Route::post('/admin/pins/{pin}/ban', [AdminPinController::class, 'ban'])->name('admin.pins.ban');
});
