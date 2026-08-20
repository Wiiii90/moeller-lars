<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Canonical persistence contract for tables that intentionally contain one row.
 * Runtime callers use current() instead of knowing the storage key.
 */
trait SingletonRecord
{
    public static function current(): static
    {
        /** @var static|null $record */
        $record = static::query()->find(static::singletonKey());

        if (! $record instanceof static) {
            throw new LogicException(class_basename(static::class).' singleton is missing.');
        }

        return $record;
    }

    public static function singletonKey(): int
    {
        return 1;
    }

    protected static function bootSingletonRecord(): void
    {
        static::creating(function (Model $record): void {
            if ($record->getKey() === null) {
                $record->setAttribute($record->getKeyName(), static::singletonKey());
            }
        });

        static::saving(function (Model $record): void {
            if ((int) $record->getKey() !== static::singletonKey()) {
                throw new LogicException(class_basename(static::class).' must use its canonical singleton key.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException(class_basename(static::class).' singleton cannot be deleted.');
        });
    }
}
