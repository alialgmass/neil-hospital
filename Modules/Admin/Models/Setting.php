<?php

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Setting extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['key', 'value', 'group'];

    /**
     * Get a setting value by key.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    /**
     * Set a setting value by key (upsert).
     */
    public static function setValue(string $key, mixed $value, string $group = 'general'): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group],
        );
    }

    /**
     * Scope to a specific group.
     */
    public function scopeGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
    }

    /**
     * URL of the uploaded hospital logo, or null if none has been set.
     */
    public static function logoUrl(): ?string
    {
        $setting = static::firstOrCreate(['key' => 'hospital_logo'], ['group' => 'hospital']);

        return $setting->getFirstMediaUrl('logo') ?: null;
    }
}
