<?php declare(strict_types=1);
namespace App\Events;
use App\Models\Upload;

class UploadPruning extends Event
{
    public function __construct(public readonly Upload $upload) { }
}
