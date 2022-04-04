<?php declare(strict_types=1);
namespace App\Jobs;
use App\Repositories\UploadRepo;
use App\Services\GcloudStorageService;
use Illuminate\Support\Carbon;

class CleanUpStaleUploads extends Job
{
    public function handle(UploadRepo $uploads, GcloudStorageService $gcs): void
    {
        $start = Carbon::now();
        $staleUploads = $uploads->listStale();
        $markBuried = [ ];
        foreach ($staleUploads as $upload) {
            $gcs->delete($upload->gcs_path, onlyIfPresent: true);
            if ($upload->pending_removal_since === null) {
                $markBuried[] = $upload;
            }
        }

        $buriedUploads = $uploads->filterBuried($staleUploads, $start);
        foreach ($buriedUploads as $upload) {
            $upload->delete();
        }

        foreach ($markBuried as $upload) {
            $upload->pending_removal_since = $start;
            $upload->save();
        }
    }
}
