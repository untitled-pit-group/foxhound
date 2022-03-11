<?php declare(strict_types=1);
namespace App\Providers;
use Laravel\Lumen\Providers\EventServiceProvider as ServiceProvider;
use App\Events;
use App\Listeners;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // event::class => [listener::class]
    ];
    protected $subscribe = [
        Listeners\GcloudStorageCleanupSubscriber::class,
    ];

    /**
     * @see https://laravel.com/docs/9.x/events#event-discovery
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
