<?php

namespace App\Domain\Admin;

use Closure;

final class AdminUndoContext
{
    private int $receiptSuppressionDepth = 0;

    public function receiptsSuppressed(): bool
    {
        return $this->receiptSuppressionDepth > 0;
    }

    public function withoutReceipts(Closure $callback): mixed
    {
        $this->receiptSuppressionDepth++;

        try {
            return $callback();
        } finally {
            $this->receiptSuppressionDepth--;
        }
    }
}
