<?php declare(strict_types=1);
namespace App\Models\Support;
use Illuminate\Database\Eloquent\Model;
use App\Support\Id;

/**
 * Base class for an Eloquent model posessing a 64-bit integer primary key
 * that's uniformly distributed. Assumes that the database integer type is
 * signed, and that PHP is running on a 64-bit platform. All insertions using
 */
abstract class RandomIdModel extends Model
{
    public $incrementing = false;

    /**
     * Generate a new ID suitable for insertion in a database. Sets the
     * corresponding ID attribute on this model.
     *
     * To avoid race conditions, this should ever be called only in a
     * transaction along with the corresponding INSERT operation.
     */
    public function generateId(): void
    {
        $table = $this->getTable();
        $key = $this->getKeyName();
        do {
            $id = Id::generate();
            $exists = app('db')->table($table)
                ->where($key, '=', $id)
                ->exists();
        } while ($exists);
        $this->$key = $id;
    }
}
