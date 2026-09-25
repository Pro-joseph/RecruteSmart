<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * EF-1204 / ENF-11: journal of sensitive actions (CV views, transfers,
 * deletions, link regeneration). Never throws and never records personal
 * data beyond identifiers — logging must not become a leak.
 */
class AuditLogger
{
    /**
     * @param  array<string, scalar|null>|null  $metadata
     */
    public function log(
        ?User $user,
        AuditAction $action,
        Model $subject,
        ?array $metadata = null,
        ?string $ip = null,
    ): void {
        try {
            if (! Schema::hasTable('audit_logs')) {
                return;
            }

            DB::table('audit_logs')->insert([
                'user_id' => $user?->id,
                'action' => $action->value,
                'subject_type' => $subject->getTable(),
                'subject_id' => $subject->getKey(),
                'metadata' => $metadata === null || $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
                'ip' => $ip,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('audit_log_failed', [
                'action' => $action->value,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
