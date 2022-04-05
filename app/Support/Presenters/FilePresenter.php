<?php declare(strict_types=1);
namespace App\Support\Presenters;
use App\Models\{File, FileIndexingState};
use App\Models\FileIndexingState\IndexingState;
use App\Support\Id;
use Illuminate\Support\Carbon;

class FilePresenter
{
    public function presentIndexingState(IndexingState $state): int
    {
        return match ($state) {
            IndexingState::QUEUED => 0,
            IndexingState::TRANSFORM => 1,
            IndexingState::TRANSCRIBE => 2,
            IndexingState::INGEST => 3,
            IndexingState::FINISHED => 4,
            IndexingState::ERROR => -1,
        };
    }

    public function present(File $file, ?FileIndexingState $fileIndexingState): array
    {
        $indexingState = $fileIndexingState?->state;
        $indexingState ??= IndexingState::QUEUED;
        $indexingState = $this->presentIndexingState($indexingState);

        $repr = [
            'id' => Id::encode($file->id),
            'name' => $file->name,
            // TODO[pn]: Do this when tags is a set instead of an array (#22).
            //'tags' => $file->tags->toArray(),
            'tags' => $file->tags,
            'upload_timestamp' => $file->upload_timestamp->format(Carbon::ATOM),
            'relevance_timestamp' => $file->relevance_timestamp?->format(Carbon::ATOM),
            'length' => $file->length,
            'hash' => $file->hash->hex(),
            'indexing_state' => $indexingState,

            // TODO: See issue #26. No database representation for file type yet.
            'type' => 'plain',
        ];

        if ($fileIndexingState?->state === IndexingState::ERROR) {
            // TODO: No database representation yet.
            $repr['removal_deadline'] = '2000-01-01T00:00:00Z';
        }

        return $repr;
    }
}
