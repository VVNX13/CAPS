<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key/value store backed by the legacy `settings` table.
 *
 * The table uses `key_name` as its primary key and has no `created_at` column.
 */
class Setting extends Model
{
    protected $table = 'settings';

    protected $primaryKey = 'key_name';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'key_name',
        'value_text',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = static::query()->where('key_name', $key)->value('value_text');

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['key_name' => $key],
            ['value_text' => $value]
        );
    }
}
