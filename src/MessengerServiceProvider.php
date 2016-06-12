<?php

namespace Jacobcyl\Messenger;

use Illuminate\Support\ServiceProvider;

class MessengerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/config/config.php' => config_path('messenger.php')
        ], 'config');

        $this->publishes([
            __DIR__ . '/migrations' => database_path('migrations')
        ], 'migrations');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/config.php', 'messenger'
        );
    }
}
