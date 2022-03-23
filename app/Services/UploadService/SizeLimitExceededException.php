<?php declare(strict_types=1);
namespace App\Services\UploadService\SizeLimitExceededException;

class SizeLimitExceededException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct("A configured size limit has been exceeded.");
    }
}
