<?php

namespace App\Domain\Publication;

use Illuminate\Support\Facades\DB;

final class CommittedRead
{
    /**
     * @template TValue
     * @param callable(): TValue $callback
     * @return TValue
     */
    public function run(callable $callback): mixed
    {
        return DB::transaction(function () use ($callback): mixed {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            DB::statement('SET LOCAL search_path TO committed, public');

            return $callback();
        }, attempts: 1);
    }
}
