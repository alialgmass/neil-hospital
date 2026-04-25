<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    public function log(
        string $action,
        string $module,
        ?string $recordId = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        $user = Auth::user();

        DB::table('activity_logs')->insert([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'module' => $module,
            'record_id' => $recordId,
            'description' => $description,
            'old_values' => $oldValues !== null ? json_encode($oldValues) : null,
            'new_values' => $newValues !== null ? json_encode($newValues) : null,
            'ip_address' => Request::ip(),
            'created_at' => now(),
        ]);
    }

    public function list(array $filters = [], int $perPage = 50)
    {
        return DB::table('activity_logs')
            ->leftJoin('users', 'activity_logs.user_id', '=', 'users.id')
            ->select('activity_logs.*', 'users.name as user_name')
            ->when($filters['module'] ?? null, fn ($q, $v) => $q->where('activity_logs.module', $v))
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('activity_logs.user_id', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('activity_logs.created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('activity_logs.created_at', '<=', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where('activity_logs.description', 'like', "%{$v}%"))
            ->orderByDesc('activity_logs.created_at')
            ->paginate($perPage);
    }

    public function getModules()
    {
        return DB::table('activity_logs')->distinct()->orderBy('module')->pluck('module');
    }

    public function getUsers()
    {
        return DB::table('users')->select('id', 'name')->orderBy('name')->get();
    }
}
