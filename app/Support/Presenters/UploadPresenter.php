<?php declare(strict_types=1);
namespace App\Support\Presenters;
use App\Models\Upload;
use App\Support\Id;

class UploadPresenter
{
    public function present(Upload $upload): array
    {
        return [
            'id' => Id::encode($upload->id),
            'name' => $upload->name,
            'progress' => $upload->progress,
        ];
    }
}
