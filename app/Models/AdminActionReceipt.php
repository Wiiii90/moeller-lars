<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use JsonException;

#[Fillable([
    'audit_event_id',
    'admin_user_id',
    'action_key',
    'inverse_action_key',
    'entity_type',
    'entity_id',
    'before_state',
    'after_state',
    'media_asset_id',
    'artwork_media_id',
    'neighbor_artwork_media_id',
    'previous_artwork_media_id',
    'next_artwork_media_id',
    'before_position',
    'after_position',
    'inverse_direction',
    'snapshot_payload',
    'receipt_version',
    'expires_at',
    'undone_at',
    'created_at',
])]
#[Guarded(['id'])]
final class AdminActionReceipt extends Model
{
    public $timestamps = false;

    /** @var array<int, array<string, mixed>|null> */
    private static array $resolvedSnapshotPayloads = [];

    protected function casts(): array
    {
        return [
            'media_asset_id' => 'integer',
            'artwork_media_id' => 'integer',
            'neighbor_artwork_media_id' => 'integer',
            'previous_artwork_media_id' => 'integer',
            'next_artwork_media_id' => 'integer',
            'before_position' => 'integer',
            'after_position' => 'integer',
            'snapshot_payload_id' => 'integer',
            'receipt_version' => 'integer',
            'expires_at' => 'datetime',
            'undone_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function auditEvent(): BelongsTo
    {
        return $this->belongsTo(AuditEvent::class);
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    protected function snapshotPayload(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): ?array {
                $payloadId = (int) ($attributes['snapshot_payload_id'] ?? 0);
                if ($payloadId < 1 || DB::connection()->getDriverName() !== 'pgsql') {
                    return $this->decodeSnapshotPayload($value);
                }

                if (! array_key_exists($payloadId, self::$resolvedSnapshotPayloads)) {
                    $payload = DB::table('history_payloads')
                        ->where('id', $payloadId)
                        ->value('payload');

                    self::$resolvedSnapshotPayloads[$payloadId] = $this->decodeSnapshotPayload($payload);
                }

                return self::$resolvedSnapshotPayloads[$payloadId];
            },
            set: function (mixed $value): ?string {
                if ($value === null) {
                    return null;
                }

                if (is_string($value)) {
                    return $value;
                }

                return json_encode($value, JSON_THROW_ON_ERROR);
            },
        );
    }

    /** @return array<string, mixed>|null */
    private function decodeSnapshotPayload(mixed $payload): ?array
    {
        if (is_array($payload)) {
            return $payload;
        }
        if (! is_string($payload) || $payload === '') {
            return null;
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
