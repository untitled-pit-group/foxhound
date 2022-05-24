<?php declare(strict_types=1);
namespace App\Jobs;
use App\Models\{File, FileIndexingState};
use App\Models\File\FileType;
use App\Models\FileIndexingState\IndexingState;
use App\Services\GcloudStorageService;
use Illuminate\Support\Carbon;

class IndexPlaintext extends Job
{
    public function __construct(protected File $file) { }

    private ?FileIndexingState $indexingState = null;

    public function handle(GcloudStorageService $gcs): void
    {
        try {
            $this->doHandle($gcs);
        } catch (\Throwable $exc) {
            if ($this->indexingState !== null) {
                $this->indexingState->state = IndexingState::ERROR;
                $this->indexingState->error_context = [
                    'message' => 'Sorry, an error has occured.',
                    'exception' => get_class($exc),
                    'exception_message' => $exc->getMessage(),
                    'location' =>
                        sprintf('%s:%d',
                            $exc->getFile() ?: '<unknown>',
                            $exc->getLine() ?: 0),
                    'trace' => $exc->getTraceAsString(),
                ];
                $this->indexingState->last_activity = Carbon::now();
                $this->indexingState->save();
            }
            throw $exc;
        }
    }

    /** @throws */
    private function doHandle(GcloudStorageService $gcs): void
    {
        $this->indexingState = FileIndexingState::where('id', $this->file->id)->first();
        if ($this->indexingState === null) {
            // TODO[pn]: Don't index
            $this->indexingState = new FileIndexingState();
            $this->indexingState->id = $this->file->id;
        }

        // HACK[pn]: This loads the entire file in RAM which is probably not
        // good for large files...?
        $stream = $gcs->downloadStream($this->file->gcs_path);
        $text = $stream->getContents();

        app('db')->insert(
            'insert into files_fulltext (id, content, locators) values (?, ?, ?)',
            [$this->file->id, $text, '\x']);
        $this->file->type = FileType::PLAIN;
        $this->indexingState->state = IndexingState::FINISHED;
        $this->indexingState->last_activity = Carbon::now();
        $this->indexingState->save();
    }
}
