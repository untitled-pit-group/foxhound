<?php declare(strict_types=1);
namespace App\Http\Rpc;
use App\Models\{File, FileIndexingState};
use App\Models\FileIndexingState\IndexingState;
use App\Rpc\RpcError;
use App\Services\GcloudStorageService;
use App\Support\{Arr, Id, NotImplementedException, RpcConstants};
use App\Support\Presenters\FilePresenter;
use Illuminate\Support\Carbon;

class FileController
{
    public function __construct(private GcloudStorageService $gcs) { }

    public function listFiles(array $params): array
    {
        // HACK[pn]: Doesn't seem that Eloquent can do eager loading of a
        // hasOne relationship (it seems to only do the inverse), so we take the
        // O(2N) hit here but do it in two queries instead of N.
        $files = File::all();
        $fileIndex = [];
        foreach ($files as $file) {
            $fileIndex[$file->id] = $file;
        }
        $fileIndexingStates = FileIndexingState::all();
        foreach ($fileIndexingStates as $fileIndexingState) {
            $fileIndex[$fileIndexingState->id]->indexingState = $fileIndexingState;
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

        $fileIndexingState = $file->indexingState?->indexing_state;
        if ($fileIndexingState === null) {
            $fileIndexingState = FileIndexingState\IndexingState::QUEUED;
        }

        $presenter = new FilePresenter();
        return $presenter->presentIndexingState($fileIndexingState);
    }

    public function getIndexingError(array $params): array
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

        $indexingState = $file->indexingState;
        if ($indexingState === null ||
            $indexingState->state != IndexingState::ERROR) {
            throw new RpcError(RpcConstants::ERROR_STATE,
                "The file has not failed indexing.");
        }

        $ctx = $indexingState->error_context;
        return [
            'stage' => 1, // TODO[pn]
            'message' => $ctx['message'],
            'log' => sprintf("%s: %s\nat %s\n%s",
                $ctx['exception'], $ctx['exception_message'],
                $ctx['location'], $ctx['trace']),
        ];
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

        $presenter = new FilePresenter();
        return $presenter->present($file, $file->indexingState);
    }

    public function requestDownload(array $params): string
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

        $url = $this->gcs->hashToGcsUrl($file->hash);
        return $this->gcs->signedDownloadUrl($url);
    }

    public function editFile(array $params): array
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

        if (array_key_exists('name', $params)) {
            if ( ! is_string($params['name'])) {
                throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                    "name must be a string.");
            }
            $file->name = $params['name'];
        }

        if (array_key_exists('tags', $params)) {
            $tags = $params['tags'];
            if ( ! is_array($tags) || Arr::any($tags, fn($t) => ! is_string($t))) {
                throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                    "tags must be an array of strings.");
            }
            $file->tags = $tags;
        }

        if (array_key_exists('relevance_timestamp', $params)) {
            $relevanceTimestamp = $params['relevance_timestamp'];
            if ($relevanceTimestamp !== null) {
                try {
                    $relevanceTimestamp = Carbon::createFromFormat(
                        Carbon::ATOM, $relevanceTimestamp);
                } catch (\Throwable $exc) {
                    throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                        "relevance_timestamp must be an RFC 3339 timestamp.");
                }
            }
            $file->relevance_timestamp = $relevanceTimestamp;
        }

        $file->save();

        $presenter = new FilePresenter();
        return $presenter->present($file, $file->indexingState);
    }

    public function deleteFile(array $params): void
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

        $this->gcs->delete($file->gcs_path, onlyIfPresent: true);

        app('db')->transaction(function ($db) use ($file) {
            $db->delete('delete from files_fulltext where id = ?', [$file->id]);
            $file->indexingState?->delete();
            $file->delete();
        });
    }

    public function editTags(array $params): array
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

        $notString = fn($t) => ! is_string($t);
        $add = $params['add'] ?? [ ];
        if (Arr::any($add, $notString)) {
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "add must be an array of strings.");
        }
        $remove = $params['remove'] ?? [ ];
        if (Arr::any($remove, $notString)) {
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "remove must be an array of strings.");
        }

        $tags = new \Ds\Set($file->tags);
        $tags->remove(...$remove);
        $tags->add(...$add);
        $file->tags = $tags->toArray();
        $file->save();

        $presenter = new FilePresenter();
        return $presenter->present($file, $file->indexingState);
    }
}
