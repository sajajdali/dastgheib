<?php

namespace App\Models\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

trait HasSaveVersion
{
    public function getSaveVersionAttribute(): string
    {
        return hash('sha256', json_encode(self::canonicalSaveAttributes($this), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    protected static function canonicalSaveAttributes(Model $model): array
    {
        $attributes = $model->getAttributes();
        ksort($attributes);
        foreach (array_keys($attributes) as $key) {
            $value = $model->getAttribute($key);
            $attributes[$key] = $value instanceof CarbonInterface ? $value->toJSON()
                : (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : ($value === null ? null : (string) $value));
        }
        return $attributes;
    }
}
