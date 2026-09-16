<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->resequenceBlogPosts();
            $this->resequenceExhibitions();
        });
    }

    public function down(): void
    {
        // This is a semantic data normalization. The previous arbitrary/import
        // order cannot be reconstructed safely after editors have reordered it.
    }

    private function resequenceBlogPosts(): void
    {
        $records = DB::table('blog_posts')
            ->select([
                'id',
                'site_section_id',
                'state',
                'position',
                'published_at',
                'scheduled_at',
                'created_at',
                'updated_at',
            ])
            ->orderBy('site_section_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy('site_section_id');

        foreach ($records as $sectionRecords) {
            $ordered = $sectionRecords
                ->sort(function (object $left, object $right): int {
                    $state = $this->blogStatePriority((string) $left->state) <=> $this->blogStatePriority((string) $right->state);
                    if ($state !== 0) {
                        return $state;
                    }

                    $date = $this->blogDateScore($right) <=> $this->blogDateScore($left);
                    if ($date !== 0) {
                        return $date;
                    }

                    return ((int) $right->id) <=> ((int) $left->id);
                })
                ->values();

            $this->persistOrder('blog_posts', $sectionRecords, $ordered);
        }
    }

    private function resequenceExhibitions(): void
    {
        $records = DB::table('exhibitions')
            ->select([
                'id',
                'site_section_id',
                'position',
                'starts_on',
                'ends_on',
                'date_text',
                'vernissage_at',
                'published_at',
                'created_at',
                'updated_at',
            ])
            ->orderBy('site_section_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy('site_section_id');

        foreach ($records as $sectionRecords) {
            $ordered = $sectionRecords
                ->sort(function (object $left, object $right): int {
                    $date = $this->exhibitionDateScore($right) <=> $this->exhibitionDateScore($left);
                    if ($date !== 0) {
                        return $date;
                    }

                    // Legacy imports were predominantly chronological ascending.
                    // For equally precise dates, prefer the later imported rank.
                    $position = ((int) $right->position) <=> ((int) $left->position);
                    if ($position !== 0) {
                        return $position;
                    }

                    return ((int) $right->id) <=> ((int) $left->id);
                })
                ->values();

            $this->persistOrder('exhibitions', $sectionRecords, $ordered);
        }
    }

    private function blogStatePriority(string $state): int
    {
        return match ($state) {
            'draft' => 0,
            'scheduled' => 1,
            'published' => 2,
            'unpublished' => 3,
            'archived' => 4,
            default => 5,
        };
    }

    private function blogDateScore(object $record): int
    {
        $value = match ((string) $record->state) {
            'scheduled' => $record->scheduled_at ?? $record->updated_at ?? $record->created_at,
            'published', 'unpublished', 'archived' => $record->published_at ?? $record->updated_at ?? $record->created_at,
            default => $record->updated_at ?? $record->created_at,
        };

        return $this->timestampScore($value);
    }

    private function exhibitionDateScore(object $record): int
    {
        $scores = [
            $this->timestampScore($record->starts_on ?? null),
            $this->timestampScore($record->ends_on ?? null),
            $this->timestampScore($record->vernissage_at ?? null),
            $this->legacyDateScore($record->date_text ?? null),
        ];
        $eventScore = max($scores);

        if ($eventScore > 0) {
            return $eventScore;
        }

        return max(
            $this->timestampScore($record->published_at ?? null),
            $this->timestampScore($record->updated_at ?? null),
            $this->timestampScore($record->created_at ?? null),
        );
    }

    private function legacyDateScore(mixed $value): int
    {
        if (! is_string($value) || trim($value) === '') {
            return 0;
        }

        $scores = [];
        if (preg_match_all('/\b(\d{1,2})\.(\d{1,2})\.(19\d{2}|20\d{2})\b/', $value, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                $timestamp = strtotime(sprintf('%04d-%02d-%02d', (int) $match[3], (int) $match[2], (int) $match[1]));
                if ($timestamp !== false) {
                    $scores[] = $timestamp;
                }
            }
        }

        if (preg_match_all('/\b(19\d{2}|20\d{2})\b/', $value, $years) > 0) {
            foreach ($years[1] as $year) {
                $timestamp = strtotime(((int) $year).'-12-31');
                if ($timestamp !== false) {
                    $scores[] = $timestamp;
                }
            }
        }

        return $scores === [] ? 0 : max($scores);
    }

    private function timestampScore(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? 0 : $timestamp;
    }

    /**
     * @param Collection<int, object> $records
     * @param Collection<int, object> $ordered
     */
    private function persistOrder(string $table, Collection $records, Collection $ordered): void
    {
        if ($ordered->isEmpty()) {
            return;
        }

        $maximum = (int) ($records->max('position') ?? 0);
        $temporaryBase = $maximum + $records->count() + 1;

        foreach ($ordered as $offset => $record) {
            DB::table($table)->where('id', $record->id)->update([
                'position' => $temporaryBase + $offset,
            ]);
        }

        foreach ($ordered as $position => $record) {
            DB::table($table)->where('id', $record->id)->update([
                'position' => $position,
            ]);
        }
    }
};
