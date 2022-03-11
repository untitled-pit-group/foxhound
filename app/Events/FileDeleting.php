<?php declare(strict_types=1);
namespace App\Events;
use App\Models\File;

class FileDeleting extends Event
{
    public function __construct(public readonly File $upload) { }
}
