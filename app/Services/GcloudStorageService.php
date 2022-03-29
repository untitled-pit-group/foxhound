<?php declare(strict_types=1);
namespace App\Services;
use Google\Cloud\Core\Exception\NotFoundException;
use Google\Cloud\Storage\{StorageClient, StorageObject};
use Illuminate\Support\Carbon;

/**
 * All of the methods on this class resolve file paths relative to the
 * configured GCS prefix (env {@code FOXHOUND_GCS_PREFIX}.
 */
class GcloudStorageService
{
    /**
     * The GCS prefix URL of where to target uploads, in form
     * {@code gs://bucket-name/path-prefix/} with a trailing slash. Apart from
     * the trailing slash, this is validated by
     * {@link App\Providers\GcloudStorageClientProvider}.
     */
    private readonly string $gcsPrefixUrl;

    public function __construct(private StorageClient $gcs)
    {
        $this->gcsPrefixUrl = rtrim(env('FOXHOUND_GCS_PREFIX'), '/');
    }


    private function relativePathToObjectInstance(string $path): StorageObject
    {
        $path = $this->gcsPrefixUrl . '/' . ltrim($path, '/');
        ['host' => $bucket, 'path' => $urlPath] = parse_url($gcsUrl);
        $urlPath = ltrim($urlPath, '/');
        $bucketInstance = $this->gcs->bucket($bucket);
        $objectInstance = $bucketInstance->object(ltrim($urlPath, '/'));
        return $objectInstance;
    }

    /**
     * Return whether the specified file exists in the storage bucket.
     *
     * Checking this can be prone to race conditions if other services can
     * create or delete the file in the meantime.
     */
    public function exists(string $path): bool
    {
        return $this->relativePathToObjectInstance($path)->exists();
    }

    /**
     * Generate a signed upload URL that can be used to begin a resumable,
     * multipart or one-part upload to the given path.
     *
     * The generated URL is valid for 24 hours, beginning with when this method
     * returns. The URL can be used to replace a file at the given path if one
     * already exists.
     */
    public function signedUploadUrl(string $path): string
    {
        return $this->relativePathToObjectInstance($path)
            ->signedUploadUrl(
                Carbon::now()->add(24, 'hours'),
                [ 'version' => 'v4' ],
            );
    }

    /**
     * Delete a file from the storage bucket.
     *
     * This function rethrows a {@link NotFoundException} if the object in
     * question does not exist, unless {@param $onlyIfPresent} is set to
     * {@code true}.
     */
    public function delete(string $path, bool $onlyIfPresent = false): void
    {
        $object = $this->relativePathToObjectInstance($path);
        if ($onlyIfPresent) {
            try {
                $object->delete();
            } catch (NotFoundException $exc) {
                //
            }
        } else {
            $object->delete();
        }
    }
}
