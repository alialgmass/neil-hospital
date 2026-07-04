<?php

namespace Modules\Admin\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SettingsService
{
    public function all(): Collection
    {
        return DB::table('settings')
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->keyBy('key');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $row = DB::table('settings')->where('key', $key)->value('value');

        return $row ?? $default;
    }

    public function updateBulk(array $settings): void
    {
        foreach ($settings as $setting) {
            $updated = DB::table('settings')
                ->where('key', $setting['key'])
                ->update(['value' => $setting['value'] ?? '']);

            if (! $updated) {
                DB::table('settings')->insertOrIgnore([
                    'key' => $setting['key'],
                    'value' => $setting['value'] ?? '',
                    'group' => $setting['group'] ?? 'general',
                ]);
            }
        }
    }
}
