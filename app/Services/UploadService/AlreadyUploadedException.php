<?php declare(strict_types=1);
namespace App\Services\UploadService;
use App\Models\File;

class AlreadyUploadedException extends \RuntimeException
{
    public function __construct(public readonly File $conflictingFile)
    {
        parent::__construct("A file with this hash has already been uploaded.");
    }
}
