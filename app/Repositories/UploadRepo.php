<?php declare(strict_types=1);
namespace App\Repositories\UploadRepo;
use App\Models\Upload;
use App\Services\GcloudStorageService\GcsUrl;
use App\Support\Sha1Hash;
use App\Support\Mixin\OptionallyBeginsTransaction;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Support\Carbon;

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
        $params = compact('hash', 'length', 'gcsPath', 'name');
        return $this->inTransaction(
            function () use ($hash, $length, $gcsPath, $name) {
                $upload = new Upload();
                $upload->hash = $hash;
                $upload->length = $length;
                $upload->gcs_path = $gcsPath;
                $upload->name = $name;
                $upload->upload_start = new Carbon('now');
                $uplaod->progress = 0.0;
                $upload->last_progress_report = null;

                $upload->generateId();
                $upload->save();

                return $upload;
            });
    }
}
