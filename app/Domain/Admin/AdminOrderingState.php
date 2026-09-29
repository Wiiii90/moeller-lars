<?php

namespace App\Domain\Admin;

final class AdminOrderingState
{
    /**
     * Stable identities for structured list items whose payload remains unchanged while only order changes.
     *
     * @param list<mixed> $items
     * @return list<string>
     */
    public static function fingerprints(array $items): array
    {
        $occurrences = [];
        $keys = [];

        foreach ($items as $item) {
            $payload = self::canonicalize($item);
            $hash = hash(
                'sha256',
                json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            );
            $occurrences[$hash] = ($occurrences[$hash] ?? 0) + 1;
            $keys[] = $hash.'#'.$occurrences[$hash];
        }

        return $keys;
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::canonicalize(...), $value);
        }

        ksort($value);
        foreach ($value as $key => $nested) {
            $value[$key] = self::canonicalize($nested);
        }

        return $value;
    }
}
