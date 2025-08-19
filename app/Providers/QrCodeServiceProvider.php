<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\QrCodeService;

class QrCodeServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(QrCodeService::class, function ($app) {
            return new QrCodeService();
        });
    }

    public function boot()
    {
        //
    }
}