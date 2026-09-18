<?php

namespace App\Domain\Publication;

use Illuminate\Support\Facades\DB;

final class CommittedRead
{
    public function __construct(
        private readonly PublicationReadContext $readContext,
    ) {}

    public function run(callable $callback): mixed
    {
        return $this->readContext->runCommitted(function () use ($callback): mixed {
            return DB::transaction(function () use ($callback): mixed {
                if (DB::transactionLevel() === 1) {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
                }

                return $callback();
            }, attempts: 1);
        });
    }
}
