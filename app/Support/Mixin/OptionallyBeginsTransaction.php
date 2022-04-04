<?php declare(strict_types=1);
namespace App\Support\Mixin;
use Illuminate\Database\DatabaseTransactionsManager;

trait OptionallyBeginsTransaction
{
    protected DatabaseTransactionsManager $databaseTransactionsManager;

    protected function inTransaction(\Closure $callback)
    {
        if ($this->databaseTransactionsManager->getTransactions()->isEmpty()) {
            return DB::transaction($callback);
        } else {
            return $callback();
        }
    }
}
