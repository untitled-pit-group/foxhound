<?php declare(strict_types=1);
namespace App\Repositories;
use App\Models\Upload;
use App\Services\GcloudStorageService\GcsUrl;
use App\Support\{Sha1Hash, Time};
use App\Support\Mixin\OptionallyBeginsTransaction;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Support\{Carbon, Collection};

class UploadRepo
{
    use OptionallyBeginsTransaction;
    public function __construct(DatabaseTransactionsManager $dtm)
    {
        $this->databaseTransactionsManager = $dtm;
    }

    public function createEmpty(
        Sha1Hash $hash,
        int $length,
        GcsUrl $gcsPath,
        string $name,
    ): Upload {
        $upload = new Upload();
        $upload->hash = $hash;
        $upload->length = $length;
        $upload->gcs_path = $gcsPath;
        $upload->name = $name;
        $upload->upload_start = new Carbon('now');
        $upload->progress = 0.0;
        $upload->last_progress_report = null;
        $this->inTransaction(function () use ($upload) {
            $upload->generateId();
            $upload->save();
        });
        return $upload;
    }

    /**
     * An upload becomes <i>stale</i>, i.e., eligible to be removed from API
     * access and its file deleted, when this many seconds pass since its
     * creation.
     */
    const UPLOAD_STALE_THRESHOLD_SEC = 24 * Time::HOUR;
    /**
     * An upload becomes <i>buried</i>, i.e., eligible for its file to be
     * deleted permanently and the upload removed from database records, when
     * this many seconds pass since the point it becomes <i>stale</i>.
     */
    const UPLOAD_BURIED_THRESHOLD_SEC = 7 * Time::DAY;

    /**
     * Return a Collection of uploads that are considered stale or buried.
     */
    public function listStale(): Collection
    {
        $staleThreshold = Carbon::now()
            ->sub(self::UPLOAD_STALE_THRESHOLD_SEC, 'seconds');
        return Upload::where('upload_start', '<', $staleThreshold)
            ->orWhereNotNull('pending_removal_since')
            ->get();
    }

    /**
     * Given a Collection of uploads, return only those which are buried and
     * should be deleted from the database.
     *
     * Before removal, all of the returned Uploads should have their file
     * removed, however, if {@link UPLOAD_BURIED_THRESHOLD_SEC} is configured
     * correctly relative to GCS's streaming upload validity rules and the
     * validity of signed upload URLs, the returned list should not have any
     * uploads that could still be in the progress of uploading.
     *
     * Given that this is sensitive relative to when the upload list was
     * obtained, a timestamp is required to be attached to the best available
     * approximation of when the listing was generated.
     */
    public static function filterBuried(Collection $uploads, Carbon $createdAt): Collection
    {
        $threshold = $createdAt->sub(self::UPLOAD_BURIED_THRESHOLD_SEC, 'seconds');
        return $uploads->filter(
            fn($upload) => $upload->pending_removal_since->isBefore($threshold));
    }

    public function select(bool $stale = false, bool $buried = false): Collection
    {
        return $this->query(stale: $stale, buried: $buried)->get();
    }

    /**
     * Get the upload, if it exists, is not stale and is not buried.
     */
    public function get(int $id, bool $stale = false, bool $buried = false): ?Upload
    {
        return $this->query(stale: $stale, buried: $buried)
            ->where('id', $id)
            ->first();
    }

    public function query(bool $stale = false, bool $buried = false)
    {
        $query = Upload::query();

        if ( ! $stale) {
            $threshold = Carbon::now()
                ->sub(self::UPLOAD_STALE_THRESHOLD_SEC, 'seconds');
            $query = $query->where('upload_start', '>=', $threshold);
        }
        if ( ! $buried) {
            $query = $query->whereNull('pending_removal_since');
        }

        return $query;
    }

    /**
     * Mark this upload as buried. After {@link UPLOAD_BURIED_THRESHOLD_SEC}
     * elapses, it will be removed from the database; until then, it won't be
     * exposed via the API anymore.
     */
    public function bury(Upload $upload): void
    {
        $upload->pending_removal_since = Carbon::now();
        $upload->save();
    }
}
