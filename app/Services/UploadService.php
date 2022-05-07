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

        $url = $this->gcs->hashToGcsUrl($hash);

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

        $upload = $this->uploads->query()
            ->where('hash', $hash->toString())
            ->first();
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
        $upload = $this->uploads->get($uploadId);
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
        $upload = $this->uploads->get($uploadId);
        if ($upload === null) {
            throw new NotFoundException();
        }
        return $upload->progress;
    }

    public function listInProgress(): Collection
    {
        return $this->uploads->select(stale: false, buried: false);
    }

    /**
     * Transform an Upload whose file is fully stored in GCS, into a File with
     * the given extra metadata.
     *
     * @link /docs/API.rst#uploads.finish
     * @throws UploadInProgressException if the upload has not yet finished,
     *         i.e., the GCS object does not exist
     */
    public function finish(
        int $uploadId,
        string $name,
        \Ds\Set $tags,
        ?\DateTimeInterface $relevanceTimestamp
    ): File {
        $upload = $this->uploads->get($uploadId);
        if ($upload === null) {
            throw new NotFoundException();
        }

        $gcsUrl = $this->gcs->hashToGcsUrl($upload->hash);
        try {
            $objectInfo = $this->gcs->getInfo($gcsUrl);
        } catch (NotFoundException $exc) {
            throw new UploadInProgressException($upload);
        }

        $size = intval($objectInfo['size']);
        if ($size > $upload->length || $size > self::MAX_UPLOAD_SIZE_BYTES) {
            // TODO[pn]: This should delete the object in GCS.
            throw new SizeLimitExceededException();
        } else if ($size !== $upload->length) {
            // TODO[pn]: This should have a more specific exception and a
            // related error in the API doc.
            // TODO[pn]: This should delete the object in GCS.
            throw new SizeLimitExceededException();
        }

        $file = File::fromUpload($upload);
        $file->name = $name;
        $file->tags = $tags->toArray();
        $file->relevance_timestamp = $relevanceTimestamp;

        app('db')->transaction(function () use ($upload, $file) {
            $file->generateId();
            $file->save();
            $upload->delete();
        });

        // TODO[pn]: This should enqueue indexing. Skipping until indexing is
        // actually implemented.
        // TODO[pn]: Indexing must check whether the file SHA-1 matches, given
        // that we can't do that here because this part is synchronous.
        // See issue #25.

        return $file;
    }
}
