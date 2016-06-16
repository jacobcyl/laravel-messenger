<?php

namespace Jacobcyl\Messenger;

use Illuminate\Support\ServiceProvider;
use Jacobcyl\Messenger\Messenger;

class MessengerServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

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

        $this->app->singleton('messenger', function($app){
            return new Messenger();
        });
    }

    public function provides()
    {
        return ['messenger'];
    }
}
