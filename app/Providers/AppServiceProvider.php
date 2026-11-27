<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\ContactMessage;
use App\Support\MapCache;
use App\Support\PinPage;
use App\Models\Device;
use App\Models\Pin;
use App\Models\Reply;
use App\Models\Report;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $record = function ($model): void {
            if (is_string($model->device_hash) && $model->device_hash !== '') {
                Device::record($model->device_hash);
            }
        };

        Pin::created($record);
        Reply::created($record);
        Report::created($record);

        $forgetMap = function (): void {
            MapCache::forget();
        };
        Pin::saved($forgetMap);
        Pin::deleted($forgetMap);
        ContactMessage::saved(fn () => Cache::forget('admin.unread-contacts'));
        ContactMessage::deleted(fn () => Cache::forget('admin.unread-contacts'));

        $unreadContacts = function (): int {
            return Cache::remember('admin.unread-contacts', 30, function (): int {
                try {
                    return ContactMessage::query()->whereNull('read_at')->count();
                } catch (\Throwable) {
                    return 0;
                }
            });
        };

        View::composer('admin.layout', function ($view) use ($unreadContacts): void {
            $view->with('unreadContacts', $unreadContacts());
        });

        View::composer(['map', 'landing'], function ($view) use ($unreadContacts): void {
            $categories = Category::options();
            $listed = Category::listedOptions();
            $view->with([
                'categories' => $categories,
                'listedCategories' => $listed,
                'defaultCategory' => array_key_exists('other', $listed)
                    ? 'other'
                    : (array_key_first($listed) ?: 'other'),
                'citySlugs' => collect(PinPage::directory())->mapWithKeys(
                    fn (array $city): array => [$city['name'] => $city['slug']]
                )->all(),
                'unreadContacts' => auth()->check() ? $unreadContacts() : 0,
            ]);
        });
    }
}
