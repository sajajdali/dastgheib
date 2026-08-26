<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomaticSmsScenario extends Model
{
    use Auditable;

    protected $fillable = ['project_id', 'name', 'inventory_tag', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function activitySection(): string { return 'پیامک اتوماتیک'; }
    public function activityLabel(): string { return $this->name; }

    public function steps(): HasMany
    {
        return $this->hasMany(AutomaticSmsScenarioStep::class, 'scenario_id')->orderBy('sort_order');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(AutomaticSmsProject::class, 'project_id');
    }
}
