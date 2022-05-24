<?php declare(strict_types=1);
namespace App\Services;
use Google\Cloud\Core\Exception\NotFoundException as GcloudNotFoundException;
use Google\Cloud\Storage\{StorageClient, StorageObject};
use Illuminate\Support\Carbon;
use App\Services\GcloudStorageService\GcsUrl;
use App\Support\{NotFoundException, Sha1Hash, Sha256Hash};
use Psr\Http\Message\StreamInterface;

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

    private function urlToObjectInstance(GcsUrl $url): StorageObject
    {
        return $this->gcs->bucket($url->bucket)
            ->object($url->object);
    }

    /**
     * Transform a relative path to an absolute GCS {@code gs://} URL, taking
     * the URL configured via {@code FOXHOUND_GCS_PREFIX} as the base.
     */
    public function relativePathToAbsolutePath(string $path): GcsUrl
    {
        $url = $this->gcsPrefixUrl . '/' . ltrim($path, '/');
        return new GcsUrl($url);
    }

    /**
     * Transform a Sha1Hash into an absolute GCS {@code gs://} URL.
     *
     * Note: Passing a {@link Sha1Hash} for {@param $hash} is deprecated.
     *
     * @param Sha256Hash|Sha1Hash $hash
     */
    public function hashToGcsUrl($hash): GcsUrl
    {
        return $this->relativePathToAbsolutePath($hash->hex());
    }

    /**
     * Return whether the specified file exists in the storage bucket.
     *
     * Checking this can be prone to race conditions if other services can
     * create or delete the file in the meantime.
     */
    public function exists(GcsUrl $url): bool
    {
        return $this->urlToObjectInstance($url)->exists();
    }

    /**
     * Generate a signed URL that can be used to download the file. The
     * generated URL is valid for 24 hours.
     */
    public function signedDownloadUrl(GcsUrl $url): string
    {
        return $this->urlToObjectInstance($url)
            ->signedUrl(
                Carbon::now()->add(24, 'hours'),
                [ 'version' => 'v4' ],
            );
    }

    /**
     * Generate a signed upload URL that can be used to begin a resumable,
     * multipart or one-part upload to the given path.
     *
     * The generated URL is valid for 24 hours, beginning with when this method
     * returns. The URL can be used to replace a file at the given path if one
     * already exists.
     */
    public function signedUploadUrl(GcsUrl $url): string
    {
        return $this->urlToObjectInstance($url)
            ->signedUploadUrl(
                Carbon::now()->add(24, 'hours'),
                [ 'version' => 'v4' ],
            );
    }

    public function downloadStream(GcsUrl $url): StreamInterface
    {
        return $this->urlToObjectInstance($url)->downloadAsStream();
    }

    /**
     * Delete a file from the storage bucket.
     *
     * @throws NotFoundException if the object does not exist and
     *         {@param $onlyIfPresent} is not {@code true}
     */
    public function delete(GcsUrl $url, bool $onlyIfPresent = false): void
    {
        $object = $this->urlToObjectInstance($url);
        try {
            $object->delete();
        } catch (GcloudNotFoundException $exc) {
            if ( ! $onlyIfPresent) {
                throw new NotFoundException();
            }
        }
    }

    /**
     * Get information about an object. Returns an array in the same shape as
     * the underlying Google Cloud API client library.
     *
     * @throws NotFoundException if the object does not exist
     */
    public function getInfo(GcsUrl $url): array
    {
        $object = $this->urlToObjectInstance($url);
        try {
            return $object->info();
        } catch (GcloudNotFoundException $exc) {
            throw new NotFoundException();
        }
    }
}
