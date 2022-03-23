<?php declare(strict_types=1);
namespace App\Http\Rpc;
use App\Rpc\RpcError;
use App\Services\UploadService;
use App\Services\UploadService\{AlreadyUploadedException,
    SizeLimitExceededException, UploadInProgressException};
use App\Support\{Id, Math, RpcConstants};

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
            [$upload, $url] = $uploads->begin(
                hash: $hash, length: $length, name: $name);
            return [
                'upload_id' => Id::encode($upload->id),
                'upload_url' => $url,
            ];
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
}
