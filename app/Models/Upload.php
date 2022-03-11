<?php declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Carbon;
use App\Events\UploadPruning;
use App\Models\Support\RandomIdModel;

class Upload extends RandomIdModel
{
    public $timestamps = false;

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
