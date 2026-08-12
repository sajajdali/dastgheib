<?php

namespace App\Models\Concerns;

use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            ActivityLogger::created($model);
        });

        static::updated(function (Model $model) {
            ActivityLogger::updated($model);
        });

        static::deleted(function (Model $model) {
            ActivityLogger::deleted($model);
        });
    }

    public function activitySection(): string
    {
        return class_basename($this);
    }

    public function activityLabel(): string
    {
        foreach (['name', 'full_name', 'title', 'subject', 'file_number', 'lastname'] as $field) {
            if (! empty($this->{$field})) {
                return (string) $this->{$field};
            }
        }

        return class_basename($this).' #'.$this->getKey();
    }
}
