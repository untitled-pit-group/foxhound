<?php declare(strict_types=1);
namespace App\Http\Rpc;
use App\Rpc\RpcError;
use App\Services\UploadService;
use App\Services\UploadService\{AlreadyUploadedException,
    SizeLimitExceededException, UploadInProgressException};
use App\Support\{Arr, Id, Math, NotFoundException, NotImplementedException,
    RpcConstants, Sha1Hash};
use App\Support\Presenters\{FilePresenter, UploadPresenter};
use Illuminate\Support\Carbon;

class UploadController
{
    public function __construct(private UploadService $uploads) {}

    public function begin(array $params): array
    {
        ['hash' => $hash, 'length' => $length, 'name' => $name] = $params;
        if ($length > Math::MAX_SAFE_DOUBLE) {
            // Even though PHP decodes JSON numbers into native integers as long
            // as they don't have a float part, for portability it shouldn't be
            // assumed that the encoder did this intentionally. Besides, I [pn]
            // believe it's a safe assumption that nobody's going to upload
            // files in excess of 8 PiB in size.
            throw new RpcError(RpcConstants::ERROR_SIZE_LIMIT_EXCEEDED,
                "The proposed file size exceeds the limit of what can safely " .
                "transmitted over JSON.");
        }
        try {
            $hash = Sha1Hash::fromHex($hash);
        } catch (\InvalidArgumentException $exc) {
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "The hash provided is not a valid SHA-1 hash in hex format.");
        }
        try {
            [$upload, $url] = $this->uploads->begin(
                hash: $hash, length: $length, name: $name);
            $presenter = new UploadPresenter();
            return $presenter->present($upload, $url);
        } catch (SizeLimitExceededException $exc) {
            throw new RpcError(RpcConstants::ERROR_SIZE_LIMIT_EXCEEDED,
                "The proposed file size exceeds the configured limit.");
        } catch (UploadInProgressException $exc) {
            throw new RpcError(RpcConstants::ERROR_IN_PROGRESS,
                "An upload of this file is already in progress.",
                Id::encode($exc->upload->id));
        } catch (AlreadyUploadedException $exc) {
            throw new RpcError(RpcConstants::ERROR_CONFLICT,
                "This file has already been uploaded.",
                Id::encode($exc->file->id));
        }
    }

    public function cancel(array $params): void
    {
        $id = $params['upload_id'] ??
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "No upload_id provided.");
        try {
            $id = Id::decode($id);
        } catch (\Throwable $exc) {
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "upload_id is not a valid upload ID.");
        }

        try {
            $this->uploads->cancel($id);
        } catch (NotFoundException $exc) {
            throw new RpcError(RpcConstants::ERROR_NOT_FOUND,
                "upload_id does not correspond to an in-progress upload.");
        }
    }

    public function finish(array $params): array
    {
        $id = $params['upload_id'] ??
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "No upload_id provided.");
        try {
            $id = Id::decode($id);
        } catch (\Throwable $exc) {
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "upload_id is not a valid upload ID.");
        }

        $name = $params['name'] ??
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                // resisting the urge to make a Faceless Men reference
                "No name provided.");
        if ( ! is_string($name)) {
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "name must be a string.");
        }

        $tags = $params['tags'] ??
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "No tags provided.");
        if ( ! is_array($tags) || Arr::any($tags, fn($t) => ! is_string($t))) {
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "tags must be an array of strings.");
        }
        $tags = new \Ds\Set($tags);

        $relevanceTimestamp = $params['relevance_timestamp'] ?? null;
        if ($relevanceTimestamp !== null) {
            try {
                $relevanceTimestamp = Carbon::createFromFormat(
                    Carbon::ATOM, $relevanceTimestamp);
            } catch (\Throwable $exc) {
                throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                    "relevance_timestamp must be a valid RFC 3339 timestamp.");
            }
        }

        try {
            $file = $this->uploads->finish($id,
                name: $name, tags: $tags, relevanceTimestamp: $relevanceTimestamp);
        } catch (NotFoundException $exc) {
            throw new RpcError(RpcConstants::ERROR_NOT_FOUND,
                "upload_id does not correspond to an in-progress upload.");
        } catch (SizeLimitExceededException $exc) {
            throw new RpcError(RpcConstants::ERROR_SIZE_LIMIT_EXCEEDED,
                "The upload file size exceeds a limit" .
                    // TODO[pn]: See UploadService
                    " or does not match the size provided initially" .
                ".");
        }

        $presenter = new FilePresenter();
        return $presenter->present($file, null);
    }

    public function reportProgress(array $params)
    {
        $id = $params['upload_id'] ??
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "No upload_id provided.");
        try {
            $id = Id::decode($id);
        } catch (\Throwable $exc) {
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "upload_id is not a valid upload ID.");
        }
        $progress = $params['progress_length'] ??
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "No progress_length provided.");
        if ( ! is_int($progress)) {
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "progress_length must be an integer.");
        }

        try {
            $this->uploads->setProgress($id, $progress);
        } catch (NotFoundException $exc) {
            throw new RpcError(RpcConstants::ERROR_NOT_FOUND,
                "upload_id does not correspond to an in-progress upload.");
        }
        return true;
    }

    public function getProgress(array $params)
    {
        $id = $params['upload_id'] ??
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "No upload_id provided.");
        try {
            $id = Id::decode($id);
        } catch (\Throwable $exc) {
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "upload_id is not a valid upload ID.");
        }

        try {
            return $this->uploads->getProgress($id);
        } catch (NotFoundException $exc) {
            throw new RpcError(RpcConstants::ERROR_NOT_FOUND,
                "upload_id does not correspond to an in-progress upload.");
        }
    }

    public function listUploads(array $params): array
    {
        $uploads = $this->uploads->listInProgress();
        $presenter = new UploadPresenter();
        return $uploads->map($presenter->present(...))->toArray();
    }
}
