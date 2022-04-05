<?php declare(strict_types=1);
namespace App\Services;
use App\Models\{File, Upload};
use App\Repositories\UploadRepo;
use App\Services\GcloudStorageService\GcsUrl;
use App\Services\UploadService\{AlreadyUploadedException,
    SizeLimitExceededException, UploadInProgressException};
use App\Support\{NotFoundException, Sha1Hash};
use Illuminate\Support\{Carbon, Collection};

class UploadService
{
    // TODO[pn]: This should be configurable.
    const MAX_UPLOAD_SIZE_BYTES = 8 * 1024 * 1024 * 1024; // 8 GiB

    public function __construct(
        private GcloudStorageService $gcs,
        private UploadRepo $uploads,
    ) {}

    protected function hashToGcsUrl(Sha1Hash $hash): GcsUrl
    {
        return $this->gcs->relativePathToAbsolutePath($hash->hex());
    }

    /**
     * @throws AlreadyUploadedException
     * @throws SizeLimitExceededException
     * @throws UploadInProgressException
     * @return [Upload, string $url]
     */
    public function begin(Sha1Hash $hash, int $length, string $name): array
    {
        if ($length > self::MAX_UPLOAD_SIZE_BYTES) {
            throw new SizeLimitExceededException();
        }

        $url = $this->hashToGcsUrl($hash);

        $upload = app('db')->transaction(function () use ($hash, $length, $name, $url) {
            $this->checkHashConflicts($hash, $length);
            return $this->uploads->createEmpty(
                hash: $hash,
                length: $length,
                gcsPath: $url,
                name: $name,
            );

            $url = $this->gcs->signedUploadUrl($url);
            return [$upload, $url];
        });

        $url = $this->gcs->signedUploadUrl($url);
        return [$upload, $url];
    }

    protected function checkHashConflicts(Sha1Hash $hash, int $length): void
    {
        $file = File::where('hash', $hash->toString())->first();
        if ($file !== null) {
            // TODO[pn]: hash collision
            //if ($file->length !== $length) {
            throw new AlreadyUploadedException($file);
        }

        $upload = Upload::where('hash', $hash->toString())->first();
        if ($upload !== null) {
            // TODO[pn]: hash collision
            //if ($upload->length !== $length) {
            throw new UploadInProgressException($upload);
        }
    }

    /**
     * @throws NotFoundException
     */
    public function setProgress(int $uploadId, int $progressBytes): void
    {
        $upload = Upload::where('id', $uploadId)->first();
        if ($upload === null) {
            throw new NotFoundException();
        }
        $upload->progress = $progressBytes / $upload->length;
        $upload->last_progress_report = Carbon::now();
        $upload->save();
    }

    /**
     * @throws NotFoundException
     */
    public function getProgress(int $uploadId): float
    {
        $upload = Upload::where('id', $uploadId)->first();
        if ($upload === null) {
            throw new NotFoundException();
        }
        return $upload->progress;
    }

    public function listInProgress(): Collection
    {
        return $this->uploads->select(stale: false, buried: false);
    }
}
