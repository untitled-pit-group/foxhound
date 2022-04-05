<?php declare(strict_types=1);
namespace App\Http\Rpc;
use App\Models\{File, FileIndexingState};
use App\Rpc\RpcError;
use App\Services\GcloudStorageService;
use App\Support\{Id, NotImplementedException, RpcConstants};
use App\Support\Presenters\FilePresenter;

class FileController
{
    public function __construct(private GcloudStorageService $gcs) { }

    public function listFiles(array $params): array
    {
        // HACK[pn]: Doesn't seem that Eloquent can do eager loading of a
        // hasOne relationship (it seems to only do the inverse), so we take the
        // O(2N) hit here.
        $files = File::all();
        $fileIndexingStates = FileIndexingState::all();
        foreach ($fileIndexingStates as $fileIndexingState) {
            $files[$fileIndexingState->id]->indexingState = $fileIndexingState;
        }

        $presenter = new FilePresenter();
        return $files
            ->map(fn($file) => $presenter->present($file, $file->indexingState))
            ->toArray();
    }

    public function checkIndexingProgress(array $params): int
    {
        $id = $params['file_id'] ??
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "No file_id provided.");
        try {
            $id = Id::decode($id);
        } catch (\Throwable $exc) {
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "file_id is not a valid file ID.");
        }

        $file = File::where('id', $id)->first();
        if ($file === null) {
            throw new RpcError(RpcConstants::ERROR_NOT_FOUND,
                "file_id does not correspond to a file.");
        }

        $fileIndexingState = FileIndexingState::where('id', $id)
            ->first()
            ?->indexing_state;
        if ($fileIndexingState === null) {
            $fileIndexingState = FileIndexingState\IndexingState::QUEUED;
        }

        $presenter = new FilePresenter();
        return $presenter->presentIndexingState($fileIndexingState);
    }

    public function getIndexingError(array $params): array
    {
        // TODO
        throw new NotImplementedException();
    }

    public function getFile(array $params): array
    {
        $id = $params['file_id'] ??
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "No file_id provided.");
        try {
            $id = Id::decode($id);
        } catch (\Throwable $exc) {
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "file_id is not a valid file ID.");
        }

        $file = File::where('id', $id)->first();
        if ($file === null) {
            throw new RpcError(RpcConstants::ERROR_NOT_FOUND,
                "file_id does not correspond to a file.");
        }
        $indexingState = FileIndexingState::where('id', $id)->first();

        $presenter = new FilePresenter();
        return $presenter->present($file, $indexingState);
    }

    public function requestDownload(array $params): string
    {
        $id = $params['file_id'] ??
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "No file_id provided.");
        try {
            $id = Id::decode($id);
        } catch (\Throwable $exc) {
            throw new RpcError(RpcConstants::ERROR_NOT_FOUND,
                "file_id is not a valid file ID.");
        }

        $file = File::where('id', $id)->first();
        if ($file === null) {
            throw new RpcError(RpcConstants::ERROR_NOT_FOUND,
                "file_id does not correspond to a file.");
        }

        $url = $this->gcs->hashToGcsUrl($file->hash);
        return $this->gcs->signedDownloadUrl($url);
    }

    public function editFile(array $params): array
    {
        // TODO
        throw new NotImplementedException();
    }

    public function editTags(array $params): array
    {
        // TODO
        throw new NotImplementedException();
    }
}
