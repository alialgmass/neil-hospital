<?php

namespace Modules\Insurance\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Insurance\Models\InsuranceAuditLog;
use Modules\Insurance\Models\InsuranceClaim;

class InsuranceAuditService
{
    public function record(string $action, string $detail, ?User $user = null, ?InsuranceClaim $claim = null): InsuranceAuditLog
    {
        return DB::transaction(function () use ($action, $detail, $user, $claim): InsuranceAuditLog {
            $last = InsuranceAuditLog::query()->latest('seq')->lockForUpdate()->first();
            $seq = ($last?->seq ?? 0) + 1;
            $time = now()->format('Y-m-d H:i:s');
            $userId = $user?->id;
            $userName = $user?->name ?? 'النظام';
            $prevHash = $last?->hash ?? '';
            $hash = hash('sha256', implode('|', [$prevHash, $seq, $time, $userId ?? '', $action, $detail, $claim?->id ?? '']));

            return InsuranceAuditLog::create(['seq' => $seq, 'user_id' => $userId, 'user_name' => $userName, 'action' => $action, 'detail' => $detail, 'claim_id' => $claim?->id, 'prev_hash' => $prevHash, 'hash' => $hash, 'created_at' => $time]);
        });
    }

    public function verify(): bool
    {
        $previous = '';
        foreach (InsuranceAuditLog::query()->orderBy('seq')->cursor() as $log) {
            $expected = hash('sha256', implode('|', [$previous, $log->seq, $log->created_at?->format('Y-m-d H:i:s'), $log->user_id ?? '', $log->action, $log->detail, $log->claim_id ?? '']));
            if ($log->prev_hash !== $previous || ! hash_equals($expected, $log->hash)) {
                return false;
            }
            $previous = $log->hash;
        }

        return true;
    }
}
