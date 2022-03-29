<?php declare(strict_types=1);
namespace App\Providers;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Support\ServiceProvider;

class GcloudStorageClientProvider extends ServiceProvider
{
    private function validateEnvironment(): void
    {
        $gcloudCredentials = env('GOOGLE_APPLICATION_CREDENTIALS');
        if ($gcloudCredentials === null || ! file_exists($gcloudCredentials)) {
            throw new \LogicException("Configuration error: Environment " .
                "variable GOOGLE_APPLICATION_CREDENTIALS does not point to " .
                "a Google Cloud service account credentials file.");
        }

        // NOTE[pn]: This isn't used in here, but creating a service provider
        // for the sole purpose of validating the gs:// URL and instantiating
        // the GcloudStorageService -- which itself doesn't need any
        // configuration -- seems redundant too.
        $gcsPrefix = env('FOXHOUND_GCS_PREFIX', '');
        $gcsPrefixUrl = parse_url($gcsPrefix);
        if ($gcsPrefixUrl === false ||
            ($gcsPrefixUrl['scheme'] ?? null) !== 'gs' ||
            ($gcsPrefixUrl['host'] ?? null) === null ||
            ($gcsPrefixUrl['path'] ?? null) === null) {
            throw new \LogicException("Configuration error: Environment " .
                "variable FOXHOUND_GCS_PREFIX does not contain a valid gs:// " .
                "URL.");
        }
    }

    public function boot(): void
    {
        $this->validateEnvironment();
    }

    public function register(): void
    {
        $this->app->singleton(StorageClient::class, function ($app) {
            return new StorageClient();
        });
    }

    public function provides(): array
    {
        return [StorageClient::class];
    }
}
