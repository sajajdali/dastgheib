<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomaticSmsProject extends Model
{
    use Auditable;

    protected $fillable = ['name', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function activitySection(): string { return 'پیامک اتوماتیک'; }
    public function activityLabel(): string { return $this->name; }

    public function scenarios(): HasMany
    {
        return $this->hasMany(AutomaticSmsScenario::class, 'project_id')->latest();
    }
}
