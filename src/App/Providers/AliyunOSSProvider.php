<?php

namespace Yaococo\AliyunOSS\Providers;

use Illuminate\Support\ServiceProvider;

class AliyunOSSProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        $this->publishes([
            __DIR__ . '/../Config/aliyunoss.php' => config_path('aliyunoss.php'),
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
