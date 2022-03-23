<?php declare(strict_types=1);
namespace App\Services;
use App\Repositories\UploadRepo;
use App\Services\UploadService\{AlreadyUploadedException,
    SizeLimitExceededException, UploadInProgressException};

class UploadService
{
    public function __construct(private GcloudStorageService $gcs) {}

    /**
     * @return [Upload, string $url]
     */
    public function begin(string $hash, int $length, string $name): array
    {
        throw new \App\Support\NotImplementedException();

        $this->checkHashConflicts($hash, $length);
        // TODO: Check whether the size doesn't exceed the configured limit.
        // TODO: Make a new Upload with the relevant data and persist it. Don't
        // forget to set its ID properly.
        // TODO: Generate the GCS URL based on the config param.
        return [$upload, $url];
    }

    protected function checkHashConflicts(string $hash, int $length): void
    {
        // TODO: Check whether a file with this hash hasn't already been uploaded.
        // If so, throw an AlreadyUploadedException with the relevant File attached.
        // If there length differs, a hash collision has occurred; I guess a new
        // exception should be provisioned for that case?
        // TODO: Check whether a file with this hash isn't currently being uploaded.
        // If so, throw an UploadInProgressException with the relevant Upload.
    }
}
