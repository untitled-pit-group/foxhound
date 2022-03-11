<?php declare(strict_types=1);
namespace App\Listeners;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Illuminate\Queue\InteractsWithQueue;
use App\Events\{FileDeleting, UploadPruning};
use App\Services\GcloudStorageService;

/**
 * Handles any upload pruning events of file removal events and removes the
 * corresponding files from GCS.
 */
class GcloudStorageCleanupSubscriber
{
    public function __construct(private GcloudStorageService $gcs) { }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(UploadPruning::class,
            [self::class, 'handleUploadPruning']);
        $events->listen(FileDeleting::class,
            [self::class, 'handleFileDeleting']);
    }

    public function handleUploadPruning(UploadPruning $event): bool
    {
        $upload = $event->upload;
        $gcs->delete($upload->gcs_path, onlyIfPresent: true);
        return true;
    }

    public function handleFileDeleting(FileDeleting $event): bool
    {
        $file = $event->file;
        $gcs->delete($file->gcs_path);
        return true;
    }
}
