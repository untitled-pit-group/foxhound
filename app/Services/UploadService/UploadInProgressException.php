<?php declare(strict_types=1);
namespace App\Services\UploadService;
use App\Models\Upload;

class UploadInProgressException extends \RuntimeException
{
    public function __construct(public readonly Upload $upload)
    {
        parent::__construct("This file is already being uploaded elsewhere.");
    }
}
