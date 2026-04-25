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

    public function updateBulk(array $settings): void
    {
        foreach ($settings as $setting) {
            DB::table('settings')
                ->where('key', $setting['key'])
                ->update(['value' => $setting['value'] ?? '']);
        }
    }
}
