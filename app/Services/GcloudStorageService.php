<?php declare(strict_types=1);
namespace App\Services;

class GcloudStorageService
{
    /**
     * Delete a file from the default GCS bucket.
     *
     * The provided path should be a gs:// URL.
     *
     * This function throws a GcloudException if the file does not exist or is
     * not accessible, unless {@param onlyIfPresent} is set to {@code true} in
     * which case no exception is thrown.
     */
    public function delete(string $path, bool $onlyIfPresent = false): void
    {
        // TODO
    }
}
