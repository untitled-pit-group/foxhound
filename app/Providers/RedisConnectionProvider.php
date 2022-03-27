<?php declare(strict_types=1);
namespace App\Providers;
use App\Support\Data\RedisConnection;
use Illuminate\Support\ServiceProvider;

class RedisConnectionProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('foxhound.redis', function ($app) {
            // TODO: inject config
            return new RedisConnection();
        });
    }

    public function provides(): array
    {
        return ['foxhound.redis'];
    }
}
