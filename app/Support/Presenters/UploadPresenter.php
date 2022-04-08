<?php declare(strict_types=1);
namespace App\Support\Presenters;
use App\Models\Upload;
use App\Support\Id;

class UploadPresenter
{
    public function present(Upload $upload, ?string $gcsUrl = null): array
    {
        $enc = [
            'id' => Id::encode($upload->id),
            'hash' => $upload->hash->hex(),
            'name' => $upload->name,
            'progress' => $upload->progress,
        ];
        if ($gcsUrl !== null) {
            $enc['gcs_url'] = $gcsUrl;
        }
        return $enc;
    }
}
