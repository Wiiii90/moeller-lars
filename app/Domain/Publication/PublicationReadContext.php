<?php

namespace App\Domain\Publication;

final class PublicationReadContext
{
    private int $committedDepth = 0;

    public function usesCommittedSnapshot(): bool
    {
        return $this->committedDepth > 0;
    }

    public function runCommitted(callable $callback): mixed
    {
        $this->committedDepth++;

        try {
            return $callback();
        } finally {
            $this->committedDepth--;
        }
    }
}
