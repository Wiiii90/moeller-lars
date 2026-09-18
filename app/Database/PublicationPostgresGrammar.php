<?php

namespace App\Database;

use App\Domain\Publication\PublicationReadContext;
use App\Domain\Publication\PublicationSnapshot;
use Illuminate\Database\Query\Grammars\PostgresGrammar;

final class PublicationPostgresGrammar extends PostgresGrammar
{
    private int $columnWrapDepth = 0;

    public function wrap($value)
    {
        $this->columnWrapDepth++;

        try {
            return parent::wrap($value);
        } finally {
            $this->columnWrapDepth--;
        }
    }

    public function wrapTable($table, $prefix = null)
    {
        if ($this->columnWrapDepth === 0
            && is_string($table)
            && app(PublicationReadContext::class)->usesCommittedSnapshot()) {
            $table = $this->qualifyCommittedSnapshotTable($table);
        }

        return parent::wrapTable($table, $prefix);
    }

    private function qualifyCommittedSnapshotTable(string $table): string
    {
        $segments = preg_split('/\s+as\s+/i', trim($table), 2);
        if ($segments === false) {
            return $table;
        }

        $tableName = $segments[0];
        if (str_contains($tableName, '.')
            || ! in_array($tableName, PublicationSnapshot::TABLES, true)) {
            return $table;
        }

        $qualified = 'committed.'.$tableName;

        if (! isset($segments[1])) {
            return $qualified;
        }

        return $qualified.' as '.$segments[1];
    }
}
