<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportBuilderTemplate extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'columns' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function fingerprintFor(string $name, string $source, array $columns): string
    {
        $normalized = [
            'name' => trim($name),
            'source' => $source,
            'columns' => array_values($columns),
        ];

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE));
    }

    public function toFrontend(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'name' => $this->name,
            'scope' => $this->source,
            'source' => $this->source,
            'fields' => array_values($this->columns ?? []),
            'columns' => array_values($this->columns ?? []),
            'created_by' => $this->created_by,
            'creator_name' => $this->creator_name,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'custom' => true,
        ];
    }
}
