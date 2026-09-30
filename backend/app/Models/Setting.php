<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::remember('settings.all', 300, fn () => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => is_scalar($value) || $value === null ? $value : json_encode($value)]);
        Cache::forget('settings.all');
    }

    public static function defaults(): array
    {
        return [
            'site_name' => 'File Service',
            'registration_enabled' => '1',
            'default_quota_bytes' => '0',
            'max_upload_bytes' => (string) (2 * 1024 * 1024 * 1024),
            'share_links_enabled' => '1',
        ];
    }
}
