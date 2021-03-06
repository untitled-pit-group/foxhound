<?php declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Events\FileDeleting;
use App\Models\File\FileType;
use App\Models\Support\RandomIdModel;
use App\Services\GcloudStorageService\GcsUrl;
use App\Support\Sha1Hash;
use App\Support\Db\{EnumCast, HashCast, StringableCast};
use App\Support\Postgres\StringArray;

class File extends RandomIdModel
{
    public $timestamps = false;

    protected $dispatchesEvents = [
        'deleting' => FileDeleting::class,
    ];
    protected $casts = [
        'hash' => HashCast::class,
        'gcs_path' => StringableCast::class . ':' . GcsUrl::class,
        'tags' => StringArray::class,
        'upload_timestamp' => 'datetime',
        'relevance_timestamp' => 'datetime',
        'type' => EnumCast::class . ':' . FileType::class,
    ];

    public function indexingState()
    {
        return $this->hasOne(FileIndexingState::class, 'id');
    }

    public static function fromUpload(Upload $upload): File
    {
        $file = new File();
        $file->name = $upload->name;
        $file->hash = $upload->hash;
        $file->length = $upload->length;
        $file->gcs_path = $upload->gcs_path;
        $file->upload_timestamp = $upload->upload_start;
        $file->tags = [];
        $file->relevance_timestamp = null;
        $file->type = null;
        return $file;
    }
}
