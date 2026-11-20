<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Device;
use App\Models\Pin;
use App\Models\Reply;
use App\Models\Report;
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

        View::composer(['map', 'landing'], function ($view): void {
            $categories = Category::options();
            $listed = Category::listedOptions();
            $view->with([
                'categories' => $categories,
                'listedCategories' => $listed,
                'defaultCategory' => array_key_exists('other', $listed)
                    ? 'other'
                    : (array_key_first($listed) ?: 'other'),
            ]);
        });
    }
}
