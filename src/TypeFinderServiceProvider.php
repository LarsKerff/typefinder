<?php

namespace Lkrff\TypeFinder;

use Illuminate\Support\ServiceProvider;
use Lkrff\TypeFinder\Console\Commands\GenerateTypesCommand;
use Lkrff\TypeFinder\Contracts\DiscoversModels;
use Lkrff\TypeFinder\Contracts\DiscoversRelations;
use Lkrff\TypeFinder\Contracts\DiscoversSchema;
use Lkrff\TypeFinder\Discovery\DiscoverModels;
use Lkrff\TypeFinder\Discovery\DiscoverRelations;
use Lkrff\TypeFinder\Discovery\DiscoverSchema;

class TypeFinderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TypeRegistry::class);
        // Bind interfaces to concrete classes
        $this->app->bind(DiscoversModels::class, DiscoverModels::class);
        $this->app->bind(DiscoversSchema::class, DiscoverSchema::class);
        $this->app->bind(DiscoversRelations::class, DiscoverRelations::class);

        // Register the command only for Artisan (avoid loading in web requests)
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateTypesCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        // Publish config if you have one (optional)
        $this->publishes([
            __DIR__.'/../config/typefinder.php' => config_path('typefinder.php'),
        ], 'config');
    }
}
