<?php declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\FileIndexingState\IndexingState;
use App\Support\Db\EnumCast;

class FileIndexingState extends Model
{
    protected $table = 'files_indexing_state';
    protected $casts = [
        'state' => EnumCast::class . ':' . IndexingState::class,
        'error_context' => 'json',
        'last_activity' => 'datetime',
    ];

    public $incrementing = false;
    public $timestamps = false;

    public function file()
    {
        return $this->belongsTo(File::class, 'id');
    }
}
