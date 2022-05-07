<?php declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Carbon;
use App\Events\UploadPruning;
use App\Models\Support\RandomIdModel;
use App\Services\GcloudStorageService\GcsUrl;
use App\Support\Db\{HashCast, StringableCast};

class Upload extends RandomIdModel
{
    public $timestamps = false;

    protected $casts = [
        'hash' => HashCast::class,
        'gcs_path' => StringableCast::class . ':' . GcsUrl::class,
        'upload_start' => 'datetime',
        'progress' => 'float',
        'last_progress_report' => 'datetime',
        'pending_removal_since' => 'datetime',
    ];

    use Prunable;
    public function prunable()
    {
        return self::where('last_progress_report', '<=',
            Carbon::now()->sub('24 hours'));
    }

    protected function pruning(): void
    {
        event(new UploadPruning($this));
    }
}
