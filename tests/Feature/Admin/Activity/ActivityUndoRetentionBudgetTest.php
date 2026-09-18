<?php

use App\Domain\Admin\AdminActionReceiptRetentionPolicy;
use App\Models\AdminActionReceipt;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('retains Undo receipts by age count and logical byte budget without deleting Activity', function (): void {
    $actor = User::factory()->admin()->create();

    $payloadText = implode('', array_map(
        static fn (int $index): string => hash('sha256', 'undo-budget-'.$index),
        range(1, 160),
    ));

    $createReceipt = static function (int $sequence) use ($actor, $payloadText): AdminActionReceipt {
        $event = AuditEvent::query()->create([
            'admin_user_id' => $actor->getKey(),
            'action' => 'public_content_setting.updated',
            'entity_type' => 'public_content_setting',
            'entity_id' => $sequence,
            'occurred_at' => now()->addSeconds($sequence),
            'request_id' => null,
            'metadata' => null,
        ]);

        return AdminActionReceipt::query()->create([
            'audit_event_id' => $event->getKey(),
            'admin_user_id' => $actor->getKey(),
            'action_key' => 'public_content_setting.updated',
            'inverse_action_key' => 'admin.undo_applied',
            'entity_type' => 'public_content_setting',
            'entity_id' => $sequence,
            'before_state' => 'before',
            'after_state' => 'after',
            'snapshot_payload' => [
                'rows' => [[
                    'table' => 'public_content_settings',
                    'row_id' => $sequence,
                    'before' => ['payload' => $payloadText],
                    'after' => ['payload' => strrev($payloadText)],
                ]],
            ],
            'receipt_version' => 1,
            'expires_at' => now()->addDays(365),
            'created_at' => now()->addSeconds($sequence),
        ]);
    };

    $first = $createReceipt(1);
    $firstBytes = (int) $first->fresh()?->getAttribute('logical_bytes');

    expect(AdminActionReceiptRetentionPolicy::MAX_BYTES_PER_USER)->toBe(256 * 1024 * 1024)
        ->and($firstBytes)->toBeGreaterThan(4_096);

    DB::statement(
        "SELECT set_config('app.admin_undo_max_bytes', ?, true)",
        [(string) (($firstBytes * 2) + 1_024)],
    );

    $second = $createReceipt(2);
    $third = $createReceipt(3);

    expect(AdminActionReceipt::query()->whereKey($first->getKey())->exists())->toBeFalse()
        ->and(AdminActionReceipt::query()->whereKey($second->getKey())->exists())->toBeTrue()
        ->and(AdminActionReceipt::query()->whereKey($third->getKey())->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('admin_user_id', $actor->getKey())->count())->toBe(3);
});
