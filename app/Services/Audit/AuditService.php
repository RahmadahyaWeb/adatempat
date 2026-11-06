<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Record an audit log entry.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function log(
        string $action,
        ?Model $auditable = null,
        array $payload = [],
        ?User $actor = null,
    ): AuditLog {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        $resolvedActor = $actor ?? $currentUser;

        return AuditLog::create([
            'actor_id' => $resolvedActor?->id,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'payload' => empty($payload) ? null : $payload,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
